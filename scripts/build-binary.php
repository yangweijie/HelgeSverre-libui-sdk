<?php

declare(strict_types=1);

/**
 * Build a standalone executable from a PHP ui2 application.
 *
 * Usage:
 *   php scripts/build-binary.php <entry.php> [options]
 *
 * Options:
 *   --name=Tetris        App name (default: basename of entry)
 *   --icon=icon.png      App icon (PNG, ICO, or ICNS)
 *   --micro=micro.sfx    Path to phpmicro.sfx (default: searches common paths)
 *   --phar=app.phar      Reuse existing PHAR instead of building
 *   --out=./dist         Output directory (default: ./dist)
 *
 * Pipeline:
 *   1. Build PHAR from entry point
 *   2. Locate phpmicro.sfx (from static-php-cli)
 *   3. Concatenate micro.sfx + app.phar → executable
 *   4. Platform-specific packaging:
 *      - macOS:  .app bundle with .icns icon
 *      - Linux:  ELF + .desktop file
 *      - Windows: .exe with embedded .ico (via rcedit)
 *
 * Prerequisites:
 *   - PHP 8.5+ with phar, fileinfo, and mbstring extensions
 *   - static-php-cli installed (for micro.sfx) — run scripts/install-spc.sh
 *   - macOS: iconutil (Xcode) for PNG→ICNS conversion
 *   - Windows: rcedit for icon injection
 */

// ── Config ──

define('SCRIPT_DIR', __DIR__);

// ── Argument parsing ──

$args = array_slice($argv, 1);
$entry = null;
$name = null;
$iconPath = null;
$microPath = null;
$pharPath = null;
$outDir = getcwd() . '/dist';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    } elseif (str_starts_with($arg, '--icon=')) {
        $iconPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--micro=')) {
        $microPath = substr($arg, 8);
    } elseif (str_starts_with($arg, '--phar=')) {
        $pharPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--out=')) {
        $outDir = substr($arg, 6);
    } elseif ($entry === null) {
        $entry = $arg;
    }
}

if ($entry === null && $pharPath === null) {
    fwrite(STDERR, "Usage: php build-binary.php <entry.php> [options]\n");
    fwrite(STDERR, "   or: php build-binary.php --phar=app.phar [options]\n");
    exit(1);
}

// ── Resolve paths ──

$outDir = rtrim($outDir, '/');
@mkdir($outDir, 0755, true);

// If entry given but no name, derive from entry file
if ($name === null && $entry !== null) {
    $name = pathinfo(basename($entry), PATHINFO_FILENAME);
}
$name ??= 'App';

// ── Step 1: Build PHAR (or reuse existing) ──

if ($pharPath === null) {
    $pharPath = $outDir . '/' . $name . '.phar';
    $buildPhar = SCRIPT_DIR . '/build-phar.php';

    if (!is_file($buildPhar)) {
        fwrite(STDERR, "Error: build-phar.php not found at {$buildPhar}\n");
        exit(1);
    }

    fwrite(STDOUT, "=== Step 1: Build PHAR ===\n");

    $pharCmd = sprintf(
        '%s -d phar.readonly=0 %s %s --output=%s --name=%s',
        PHP_BINARY,
        escapeshellarg($buildPhar),
        escapeshellarg($entry),
        escapeshellarg($pharPath),
        escapeshellarg($name),
    );

    passthru($pharCmd, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "PHAR build failed.\n");
        exit(1);
    }
} else {
    fwrite(STDOUT, "=== Step 1: Using existing PHAR: {$pharPath} ===\n");
    if (!is_file($pharPath)) {
        fwrite(STDERR, "Error: PHAR not found: {$pharPath}\n");
        exit(1);
    }
}

// ── Step 2: Locate phpmicro.sfx ──

fwrite(STDOUT, "\n=== Step 2: Locate phpmicro.sfx ===\n");

$microCandidates = [];
if ($microPath !== null) {
    $microCandidates[] = $microPath;
}

// Common SPC output paths
$home = getenv('HOME') ?: '~';
$spcDirs = [
    getcwd() . '/php-src',
    getcwd() . '/static-php-cli',
    $home . '/.spc',
    $home . '/.static-php-cli',
    '/usr/local/lib/php',
    '/opt/homebrew/lib/php',
];

foreach ($spcDirs as $dir) {
    $microCandidates[] = $dir . '/micro.sfx';
    $microCandidates[] = $dir . '/buildroot/bin/micro.sfx';
    $microCandidates[] = $dir . '/build/micro.sfx';
    $microCandidates[] = $dir . '/php-micro/micro.sfx';
}

$resolvedMicro = null;
foreach ($microCandidates as $candidate) {
    if (is_file($candidate)) {
        $resolvedMicro = realpath($candidate);
        break;
    }
}

if ($resolvedMicro === null) {
    fwrite(STDERR, "Error: phpmicro.sfx not found.\n");
    fwrite(STDERR, "  Install it first: php scripts/install-spc.php\n");
    fwrite(STDERR, "  Or specify: --micro=/path/to/micro.sfx\n");
    fwrite(STDERR, "\n  Searched:\n");
    foreach ($microCandidates as $c) {
        fwrite(STDERR, "    - {$c}\n");
    }
    exit(1);
}

fwrite(STDOUT, "  Found: {$resolvedMicro}\n");

// ── Step 3: Concatenate micro.sfx + PHAR → binary ──

fwrite(STDOUT, "\n=== Step 3: Build executable ===\n");

$binaryName = $name;
if (PHP_OS_FAMILY === 'Windows') {
    $binaryName .= '.exe';
}
$binaryPath = $outDir . '/' . $binaryName;

$concat = sprintf('cat %s %s > %s', escapeshellarg($resolvedMicro), escapeshellarg($pharPath), escapeshellarg($binaryPath));
passthru($concat, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Error: failed to concatenate micro.sfx + PHAR.\n");
    exit(1);
}

chmod($binaryPath, 0755);
fwrite(STDOUT, "  Binary: {$binaryPath} (" . number_format(filesize($binaryPath)) . " bytes)\n");

// ── Step 4: Platform-specific packaging ──

fwrite(STDOUT, "\n=== Step 4: Platform packaging ===\n");

switch (PHP_OS_FAMILY) {
    case 'Darwin':
        packageMacOS($binaryPath, $name, $outDir, $iconPath);
        break;

    case 'Windows':
        packageWindows($binaryPath, $name, $outDir, $iconPath);
        break;

    default:
        packageLinux($binaryPath, $name, $outDir, $iconPath);
        break;
}

// ── Summary ──

fwrite(STDOUT, "\n=== Done ===\n");

// ── Platform-specific packaging functions ──

/**
 * macOS: wrap executable in .app bundle, embed icon.
 */
function packageMacOS(string $binaryPath, string $name, string $outDir, ?string $iconPath): void
{
    $appDir = $outDir . '/' . $name . '.app';
    $macOSDir = $appDir . '/Contents/MacOS';
    $resDir = $appDir . '/Contents/Resources';

    @mkdir($macOSDir, 0755, true);
    @mkdir($resDir, 0755, true);

    // Move binary into .app bundle
    $binaryDest = $macOSDir . '/' . $name;
    rename($binaryPath, $binaryDest);
    chmod($binaryDest, 0755);

    // Handle icon
    $icnsPath = null;
    if ($iconPath !== null && is_file($iconPath)) {
        $ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));

        if ($ext === 'icns') {
            $icnsPath = $iconPath;
        } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            // Convert PNG to ICNS via iconutil (requires png2icns or iconutil with iconset)
            $iconsetDir = $resDir . '/AppIcon.iconset';
            @mkdir($iconsetDir, 0755, true);

            // Generate required sizes
            $sizes = [
                [16, 16, 'icon_16x16.png'],
                [32, 32, 'icon_16x16@2x.png'],
                [32, 32, 'icon_32x32.png'],
                [64, 64, 'icon_32x32@2x.png'],
                [128, 128, 'icon_128x128.png'],
                [256, 256, 'icon_128x128@2x.png'],
                [256, 256, 'icon_256x256.png'],
                [512, 512, 'icon_256x256@2x.png'],
                [512, 512, 'icon_512x512.png'],
                [1024, 1024, 'icon_512x512@2x.png'],
            ];

            foreach ($sizes as [$w, $h, $filename]) {
                $cmd = sprintf(
                    'sips -z %d %d %s --out %s 2>/dev/null',
                    $h, $w,
                    escapeshellarg($iconPath),
                    escapeshellarg($iconsetDir . '/' . $filename),
                );
                passthru($cmd);
            }

            $icnsOut = $resDir . '/AppIcon.icns';
            $cmd = sprintf('iconutil -c icns %s -o %s 2>/dev/null', escapeshellarg($iconsetDir), escapeshellarg($icnsOut));
            passthru($cmd, $icnsExit);

            if ($icnsExit === 0 && is_file($icnsOut)) {
                $icnsPath = $icnsOut;
                // Remove temporary iconset
                $rmCmd = sprintf('rm -rf %s', escapeshellarg($iconsetDir));
                passthru($rmCmd);
            } else {
                fwrite(STDERR, "  Warning: iconutil failed, try providing a .icns file directly.\n");
            }
        }
    }

    if ($icnsPath !== null && is_file($icnsPath)) {
        copy($icnsPath, $resDir . '/AppIcon.icns');
        fwrite(STDOUT, "  Icon: AppIcon.icns installed\n");
    }

    // Generate Info.plist
    $plist = <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>{$name}</string>
    <key>CFBundleIdentifier</key>
    <string>com.yangweijie.{$name}</string>
    <key>CFBundleName</key>
    <string>{$name}</string>
    <key>CFBundleDisplayName</key>
    <string>{$name}</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleInfoDictionaryVersion</key>
    <string>6.0</string>
    <key>CFBundleIconFile</key>
    <string>AppIcon</string>
    <key>LSUIElement</key>
    <false/>
</dict>
</plist>
PLIST;

    file_put_contents($appDir . '/Contents/Info.plist', $plist);
    fwrite(STDOUT, "  .app bundle: {$appDir}\n");
    fwrite(STDOUT, "  Run: open {$appDir}\n");
}

/**
 * Linux: create executable + .desktop file.
 */
function packageLinux(string $binaryPath, string $name, string $outDir, ?string $iconPath): void
{
    // Handle icon
    if ($iconPath !== null && is_file($iconPath)) {
        $ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $iconDest = $outDir . '/' . $name . '.png';
            copy($iconPath, $iconDest);
            fwrite(STDOUT, "  Icon: {$iconDest}\n");
        }
    }

    // Generate .desktop file
    $desktop = <<<DESKTOP
[Desktop Entry]
Type=Application
Name={$name}
Exec={$binaryPath}
Icon={$outDir}/{$name}.png
Terminal=false
Categories=Game;Utility;
DESKTOP;

    $desktopPath = $outDir . '/' . $name . '.desktop';
    file_put_contents($desktopPath, $desktop);
    chmod($desktopPath, 0755);

    fwrite(STDOUT, "  Binary: {$binaryPath}\n");
    fwrite(STDOUT, "  Desktop: {$desktopPath}\n");
}

/**
 * Windows: inject icon into .exe via rcedit.
 */
function packageWindows(string $binaryPath, string $name, string $outDir, ?string $iconPath): void
{
    if ($iconPath !== null && is_file($iconPath)) {
        $ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));

        if ($ext === 'ico') {
            // Try rcedit
            $rcedit = findExecutable('rcedit');
            if ($rcedit !== null) {
                $cmd = sprintf(
                    '%s %s --set-icon %s',
                    escapeshellarg($rcedit),
                    escapeshellarg($binaryPath),
                    escapeshellarg($iconPath),
                );
                passthru($cmd, $exitCode);

                if ($exitCode === 0) {
                    fwrite(STDOUT, "  Icon injected via rcedit\n");
                } else {
                    fwrite(STDERR, "  Warning: rcedit failed, icon not injected.\n");
                }
            } else {
                fwrite(STDERR, "  Warning: rcedit not found. Install it or manually inject the icon.\n");
            }
        } else {
            fwrite(STDERR, "  Warning: Windows requires .ico format. Convert your image first.\n");
        }
    }

    fwrite(STDOUT, "  Binary: {$binaryPath}\n");
}

// ── Utility ──

/**
 * Find an executable in PATH.
 */
function findExecutable(string $name): ?string
{
    $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
    foreach ($paths as $dir) {
        $candidate = $dir . '/' . $name;
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
        // Windows
        $candidate .= '.exe';
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}
