<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\System\Tray;

/**
 * Tray icon demo — shows a system tray icon with context menu.
 *
 * Run: php85 examples/test-tray.php
 *
 * Note: macOS may not show tray icons for CLI-launched apps reliably.
 * For production use, wrap in a proper .app bundle.
 */

$window = new Window('Tray Demo', 400, 300, true);

$label = new Label('Check the system tray (menu bar) for the tray icon.' . PHP_EOL
    . 'Right-click (or left-click) to see the context menu.');

$window->setChild($label);

// Create the App instance early so tray callbacks can reference it
$app = App::new();

// Create tray icon — must be done before App starts the event loop
$tray = new Tray($window, __DIR__ . '/../assets/icon.png');

// Build the context menu
$tray
    ->addItem('Show Window', function () use ($window): void {
        $window->show();
    })
    ->addSeparator()
    ->addItem('Say Hello', function () use ($label): void {
        $label->setText('Hello from tray!');
    })
    ->addItem('Reset Label', function () use ($label, $window): void {
        $label->setText('Check the system tray (menu bar) for the tray icon.');
    })
    ->addSeparator()
    ->addItem('Quit', function () use ($window, $tray): void {
        $tray->remove();
        Ffi::quit();
    })
    ->attach();

echo "Tray icon created. Check the menu bar.\n";

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
