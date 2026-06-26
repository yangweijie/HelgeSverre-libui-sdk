<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Label;
use Libui\Window;
use Libui\Loop;
use Yangweijie\Ui2\System\GlobalHotkey;

/**
 * Global hotkey demo — register system-wide shortcuts.
 *
 * Run: php85 examples/test-global-hotkey.php
 *
 * Registered shortcuts:
 *   Cmd+Shift+H  — Shows/Hides the window
 *   Cmd+Shift+Q  — Quits the app
 */

$window = new Window('Global Hotkey Demo', 400, 200, true);

$label = new Label(
    "Global hotkeys registered:" . \PHP_EOL
    . "  Cmd+Shift+H  — Toggle window visibility" . \PHP_EOL
    . "  Cmd+Shift+Q  — Quit app" . \PHP_EOL
    . \PHP_EOL
    . "These work even when the window is minimized." . \PHP_EOL
    . "Press Cmd+Shift+H now to hide this window."
);

$window->setChild($label);

// Create and register hotkeys
$hotkey = new GlobalHotkey();
$hotkeyRegisterCount = 0;
$toggleVisible = true; // Track visibility state

try {
    $hotkey->register('Cmd+Shift+H', function () use ($window): void {
        if ($window->visible()) {
            $window->hide();
        } else {
            $window->show();
        }
    });
    $hotkeyRegisterCount++;

    $hotkey->register('Cmd+Shift+Q', function () use ($hotkey): void {
        $hotkey->unregisterAll();
        Loop::stop();
    });
    $hotkeyRegisterCount++;

    echo "Registered {$hotkeyRegisterCount} hotkey(s). Starting polling...\n";

    // Start polling (checks hotkey state every 100ms)
    $hotkey->startPolling(100);

} catch (\RuntimeException $e) {
    echo "Hotkey registration failed: " . $e->getMessage() . "\n";
    echo "The demo will run without hotkeys.\n";
}

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();