<?php
/**
 * Install .opencode skill to project root
 * Usage: composer install:opencode
 * 
 * This script is run from the dependent project's root directory.
 * The script location is vendor/yangweijie/ui2/scripts/install-opencode.php
 * The package root is vendor/yangweijie/ui2/
 */

// Get the package root (vendor/yangweijie/ui2/)
$packageRoot = dirname(__DIR__);

// Source is package root .opencode
$src = $packageRoot . '/.opencode';

// Destination is current working directory (project root)
$dst = getcwd() . '/.opencode';

if (!is_dir($src)) {
    echo "Source .opencode not found at $src\n";
    exit(1);
}

if (is_dir($dst)) {
    echo "Destination .opencode already exists at $dst\n";
    echo "Remove it first or backup manually.\n";
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($src) + 1);
    $target = $dst . DIRECTORY_SEPARATOR . $relative;
    if ($item->isDir()) {
        @mkdir($target, 0755, true);
    } else {
        @mkdir(dirname($target), 0755, true);
        copy($item->getPathname(), $target);
    }
}

echo "Successfully installed .opencode to $dst\n";