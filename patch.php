<?php

// 或者更好的方式：完全使用 PHP 实现，避免调用系统命令
function copyPatchesSafely() {
    $source = 'patches/';
    $destination = 'vendor/';

    if (!is_dir($source)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($source));
        $target = $destination . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }

    return true;
}

copyPatchesSafely();
