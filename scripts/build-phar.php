<?php

declare(strict_types=1);

/**
 * Build a PHAR archive from a PHP entry file.
 *
 * Usage:
 *   php scripts/build-phar.php <entry.php> [--output=app.phar] [--name=AppName]
 *
 * Bundles the entry file, runtime composer dependencies, and platform-specific
 * native libraries. The PHAR stub extracts native libs to temp at startup for FFI.
 *
 * Works from any project that depends on yangweijie/ui2:
 *   php vendor/yangweijie/ui2/scripts/build-phar.php my-app.php
 */

// ── Argument parsing ──

$args = array_slice($argv, 1);
$entry = null;
$output = 'app.phar';
$name = 'App';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $output = substr($arg, 9);
    } elseif (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    } elseif ($entry === null) {
        $entry = $arg;
    }
}

if ($entry === null) {
    fwrite(STDERR, "Usage: php build-phar.php <entry.php> [--output=app.phar] --name=AppName\n");
    exit(1);
}

// ── Resolve paths ──

$entryReal = realpath($entry);
if ($entryReal === false || !is_file($entryReal)) {
    fwrite(STDERR, "Error: entry file not found: {$entry}\n");
    exit(1);
}

// Find project root — first parent containing vendor/
$projectRoot = dirname($entryReal);
while (true) {
    if (is_dir($projectRoot . '/vendor')) {
        break;
    }
    $parent = dirname($projectRoot);
    if ($parent === $projectRoot) {
        fwrite(STDERR, "Error: cannot find vendor/ directory (project root).\n");
        exit(1);
    }
    $projectRoot = $parent;
}

$vendorDir = $projectRoot . '/vendor';

// Derive relative path from project root to entry file
$entryRelative = substr($entryReal, strlen($projectRoot) + 1);

fwrite(STDOUT, "Project root: {$projectRoot}\n");
fwrite(STDOUT, "Entry:        {$entryRelative}\n");
fwrite(STDOUT, "Output:       {$output}\n");

if (ini_get('phar.readonly')) {
    ini_set('phar.readonly', '0');
}

if (is_file($output)) {
    unlink($output);
}

// ── Get runtime package vendor paths ──

function getRuntimePackageDirs(string $projectRoot, string $vendorDir): array
{
    $cmd = sprintf('cd %s && composer show --no-dev --name-only 2>/dev/null', escapeshellarg($projectRoot));
    $output = shell_exec($cmd);
    if ($output === null || $output === '') {
        return [];
    }

    $pkgs = array_map('trim', array_filter(explode("\n", $output)));
    $dirs = [];
    foreach ($pkgs as $pkg) {
        $parts = explode('/', $pkg);
        if (count($parts) !== 2) {
            continue;
        }
        $dir = $vendorDir . '/' . $parts[0] . '/' . $parts[1];
        if (is_dir($dir)) {
            $dirs[$pkg] = $dir;
        }
    }
    return $dirs;
}

// ── Check if a relative path (under vendor/) matches runtime packages ──

function isRuntimeVendorPath(string $relative, array $pkgDirs, string $vendorDir): bool
{
    // Check if this path is under any of the runtime package directories
    foreach ($pkgDirs as $pkgDir) {
        $pkgRelative = substr($pkgDir, strlen($vendorDir) + 1); // e.g. "illuminate/support"
        if (str_starts_with($relative, $pkgRelative . '/') || $relative === $pkgRelative) {
            return true;
        }
    }
    // Also allow vendor/composer/ and vendor/autoload.php
    if (str_starts_with($relative, 'composer/') || $relative === 'autoload.php') {
        return true;
    }
    return false;
}

// ── Check if path matches exclude patterns ──

function isExcluded(string $relative, array $patterns): bool
{
    $base = basename($relative);
    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, $relative, FNM_PATHNAME) || fnmatch($pattern, $base)) {
            return true;
        }
    }
    return false;
}

// ── Exclude patterns ──

$excludePatterns = [
    '*.md', '*.MD', 'LICENSE', 'LICENSE.*', 'COPYING',
    'composer.json', 'composer.lock', 'CHANGELOG*',
    '.git', '.github', '.gitignore', '.gitattributes',
    'Makefile', 'Dockerfile', '.dockerignore',
    'phpunit.xml*', 'phpstan*', 'pint.json', 'ecs.php',
    '.php-cs-fixer*', 'rector.php', 'infection.json*',
    '*.phpt', 'test*', 'Test*', 'tests', 'Tests',
    'docs', 'doc', 'example*', 'Example*',
    'bin', 'bin/*',
    '.*',
];

fwrite(STDOUT, "Building PHAR...\n");

try {
    $phar = new Phar($output, 0, $name . '.phar');
    $phar->startBuffering();

// ── Phase 1: Collect all vendor files into a flat list ──
// Strategy: include everything in vendor/ except known-unnecessary paths.
// Filtering by package list breaks transitive deps (e.g. symfony/polyfill-intl-grapheme),
// so we use a negative exclusion approach instead.

fwrite(STDOUT, " Scanning vendor/...\n");

$skipDirs = ['bin', 'tests', 'Test', 'docs', 'doc', 'documentation', '.github', 'node_modules', 'tmp', 'examples'];
$binaryExts = ['exe', 'so', 'dylib', 'a', 'o', 'obj', 'bin', 'dat', 'db'];

// ── RecursiveCallbackFilterIterator for vendor/ ──
// Returns true to KEEP, false to REJECT

$vendorFilter = function (SplFileInfo $current, mixed $key, RecursiveDirectoryIterator $iterator) use ($vendorDir, $skipDirs, $binaryExts): bool {
    $relPath = substr($current->getPathname(), strlen($vendorDir) + 1);

    // For directories: skip excluded top-level dirs (prevents recursion)
    if ($current->isDir()) {
        $first = strstr($relPath, DIRECTORY_SEPARATOR, true);
        $topDir = $first !== false ? $first : $relPath;
        return !in_array($topDir, $skipDirs, true) && !str_starts_with($topDir, 'Test');
    }

    // For files: apply exclusion rules
    if (!$current->isFile()) return false;

    // Platform-specific libui libs: only include current platform
    if (str_starts_with($relPath, 'helgesverre' . DIRECTORY_SEPARATOR . 'libui' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR)) {
        $pd = match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Linux' => 'linux-x86_64',
            'Windows' => 'windows-x86_64',
            default => null,
        };
        if ($pd === null) return false;
        $prefix = 'helgesverre' . DIRECTORY_SEPARATOR . 'libui' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $pd . DIRECTORY_SEPARATOR;
        if (!str_starts_with($relPath, $prefix)) return false;
        // Skip backup/versioned dylibs
        $base = $current->getBasename();
        if ($base === '.DS_Store' || str_contains($base, '.bak') || str_ends_with($base, '2.dylib') || str_ends_with($base, '3.dylib')) return false;
        return true;
    }

    // Platform-specific pebview libs
    if (str_starts_with($relPath, 'kingbes' . DIRECTORY_SEPARATOR . 'pebview' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR)) {
        $pd = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => null,
        };
        if ($pd === null) return false;
        $prefix = 'kingbes' . DIRECTORY_SEPARATOR . 'pebview' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $pd . DIRECTORY_SEPARATOR;
        if (!str_starts_with($relPath, $prefix)) return false;
        $base = $current->getBasename();
        if ($base === '.DS_Store') return false;
        return true;
    }

    // Skip binaries outside lib/
    $ext = strtolower($current->getExtension());
    if (in_array($ext, $binaryExts, true) && $ext !== 'dll') return false; // allow .dll in PebView

    return true;
};

$vendorInner = new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS);
$vendorFiltered = new RecursiveCallbackFilterIterator($vendorInner, $vendorFilter);
$vendorIt = new RecursiveIteratorIterator($vendorFiltered, RecursiveIteratorIterator::LEAVES_ONLY);

fwrite(STDOUT, " Adding vendor/ files via buildFromIterator...\n");
$phar->buildFromIterator($vendorIt, $projectRoot);
fwrite(STDOUT, " Added vendor/ files\n");

    // ── Add entry file ──

    fwrite(STDOUT, "  Adding entry: {$entryRelative}\n");
    $phar->addFile($entryReal, $entryRelative);

    // ── Add ui2 source ──

    // Case 1: ui2 is in vendor/ (third-party project) — already added above
    // Case 2: we ARE the ui2 project — add src/ and bootstrap.php
    $ui2Src = $projectRoot . '/src';
    if (is_dir($ui2Src) && !is_dir($projectRoot . '/vendor/yangweijie/ui2')) {
        fwrite(STDOUT, "  Adding ui2 src/ (project root)...\n");
        $srcIt = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ui2Src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($srcIt as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($projectRoot) + 1);
            if (isExcluded($relative, $excludePatterns)) {
                continue;
            }
            $phar->addFile($item->getPathname(), $relative);
        }
        $bs = $projectRoot . '/bootstrap.php';
        if (is_file($bs)) {
            $phar->addFile($bs, 'bootstrap.php');
        }
    }

    // Add patches/ if present
    $patchesDir = $vendorDir . '/yangweijie/ui2/patches';
    if (is_dir($patchesDir)) {
        fwrite(STDOUT, "  Adding ui2 patches/...\n");
        $patchIt = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($patchesDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($patchIt as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = 'vendor/yangweijie/ui2/patches/' . substr($item->getPathname(), strlen($patchesDir) + 1);
            $phar->addFile($item->getPathname(), $relative);
        }
    }

    // ── Build PHAR stub ──

    $entryPharPath = $entryRelative;

    $stub = <<<"STUB"
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PHAR bootstrap for {$name}.
 *
 * Extracts native shared libraries to a temporary directory so FFI can
 * load them via dlopen() — which does not support phar:// streams.
 */

Phar::mapPhar();

// ── Extract native libraries to real filesystem ──

\$extractKey = 'ui2_' . md5(__FILE__);
\$extractDir = sys_get_temp_dir() . '/' . \$extractKey;

if (!is_dir(\$extractDir . '/vendor/helgesverre/libui/lib')) {
    @mkdir(\$extractDir, 0755, true);
    \$phar = new Phar(__FILE__);

    // Extract native libs (libui shared libraries)
    \$phar->extractTo(\$extractDir, 'vendor/helgesverre/libui/lib/');

    // Extract FFI header
    \$phar->extractTo(\$extractDir, 'vendor/helgesverre/libui/src/Native/');

    fwrite(STDERR, "[{$name}] Extracted native libraries to: {\$extractDir}\\n");
}

// Point Ffi::libPath() to the extracted native libs via LIBUI_LIB env.
\$libDir = \$extractDir . '/vendor/helgesverre/libui/lib';
\$arch = strtolower(php_uname('m'));
\$isArm = str_contains(\$arch, 'aarch64') || str_contains(\$arch, 'arm');

\$candidates = match (PHP_OS_FAMILY) {
    'Darwin' => [\$libDir . '/darwin/libui.dylib'],
    'Windows' => [\$libDir . '/windows-x86_64/libui.dll'],
    default => \$isArm
        ? [\$libDir . '/linux-aarch64/libui.so']
        : [\$libDir . '/linux-x86_64/libui.so'],
};

foreach (\$candidates as \$lib) {
    if (is_file(\$lib)) {
        putenv("LIBUI_LIB={\$lib}");
        break;
    }
}

// ── Clean up old extractions after 7 days ──
\$tmpBase = sys_get_temp_dir();
foreach (glob(\$tmpBase . '/ui2_*') as \$oldDir) {
    if (\$oldDir !== \$extractDir && is_dir(\$oldDir)) {
        \$age = time() - filemtime(\$oldDir);
        if (\$age > 86400 * 7) {
            \$it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(\$oldDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach (\$it as \$file) {
                \$file->isDir() ? @rmdir(\$file->getRealPath()) : @unlink(\$file->getRealPath());
            }
            @rmdir(\$oldDir);
        }
    }
}

// ── Runs before the entry file ──
// Set LIBUI_LIB env so Ffi::libPath() finds the native library.
// Ffi itself is loaded via normal PSR-4 autoload when the entry runs.

// ── Run the app ──
require 'phar://' . __FILE__ . '/{$entryPharPath}';
__HALT_COMPILER();
STUB;

    $phar->setStub($stub);
    $phar->stopBuffering();

    fwrite(STDOUT, "Done: {$output} (" . number_format(filesize($output)) . " bytes)\n");

} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    if (is_file($output)) {
        unlink($output);
    }
    exit(1);
}
