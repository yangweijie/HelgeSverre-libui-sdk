<?php
/**
 * Bootstraps Libui FFI — loads Ffi.php and calls init().
 *
 * This file exists to isolate FFI initialization from the main entry file
 * (tetris.php), preventing PHP 8.5's compile-time eager class resolution
 * conflict. The main entry file MUST NOT contain any `\Libui\Ffi` FQN.
 *
 * Usage in entry file:
 *   require __DIR__ . '/../scripts/init-ffi.php';
 *   // Ffi is now ready, no \Libui\Ffi references in this file.
 */

declare(strict_types=1);

// 1. Load the class file directly (before any eager-resolution stub can form).
require __DIR__ . '/../vendor/helgesverre/libui/src/Ffi.php';

// 2. Call init() via a string variable — this is run-time only and does NOT
//    trigger PHP 8.5's compile-time "local import" eager resolution.
$class = '\Libui\Ffi';
$class::init();
