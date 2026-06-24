<?php

declare(strict_types=1);

/**
 * Menu demo — demonstrates the patched MenuItem::onClick() API.
 *
 * Run: php examples/menu.php
 *
 * Key points this example illustrates:
 *
 * - Every menu item uses onCLICK() (the patched wrapper), never the raw
 *   onCLICKED() from the generated code. onClick() hides libui's raw
 *   uiWindow* parameter and accepts an optional per-call error handler.
 * - Menus MUST be created before any Window exists (libui rule enforced
 *   at runtime by Libui\Menu via MenuOrderException).
 * - Check items use appendCheckItem() and can be toggled.
 * - Platform-special items (Quit, About) are created via dedicated
 *   methods and wired with onClick() if a custom handler is needed.
 *
 * @see patches/helgesverre/libui/src/MenuItem.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Libui\Ffi;
use Libui\Label;
use Libui\Menu;
use Libui\MenuItem;
use Libui\Window;
use Libui\Build;

Ffi::init();

// ---------------------------------------------------------------------------
// Menus — MUST come before the first Window constructor.
// ---------------------------------------------------------------------------

// --- File menu ---
$fileMenu = new Menu('File');

// appendItem() accepts an optional onClick callback directly, which uses
// the patched MenuItem::onClick() internally — no raw onClicked() needed.
$fileMenu->appendItem('Open', function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('Open', 'You clicked Open (demo only)');
});

$fileMenu->appendSeparator();

// Platform Quit item — no custom handler, so libui's default quit behaviour
// (calling uiQuit()) is preserved.  The Window::run() loop below handles
// the rest.
$fileMenu->appendQuitItem();

// --- Edit menu ---
$editMenu = new Menu('Edit');

// Clipped items manually for demonstration — attach onClick() after creation.
$cutItem  = $editMenu->appendItem('Cut');
$copyItem = $editMenu->appendCheckItem('Copy');   // checkable
$pasteItem = $editMenu->appendItem('Paste');

$cutItem->onClick(function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('Cut', 'Cut clicked (demo only)');
});

$copyItem->onClick(function (MenuItem $item) use (&$window): void {
    $checked = $item->checked() ? 'checked' : 'unchecked';
    $window->dialogs()->msgBox('Copy', "Copy is now {$checked}");
});

$pasteItem->onClick(function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('Paste', 'Paste clicked (demo only)');
});

// --- Help menu ---
$helpMenu = new Menu('Help');

// appendAboutItem() returns a MenuItem — wire onClick() manually.
$helpMenu->appendAboutItem()->onClick(function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('About', 'Menu Demo v1.0 — using patched MenuItem::onClick()');
});

// ---------------------------------------------------------------------------
// Window — menus are locked after this point.
// ---------------------------------------------------------------------------

// Use the Window constructor directly so we can set hasMenubar = true.
// (Build::window() defaults to hasMenubar = false.)
$window = new Window('Menu Demo — onCLICK() only', 420, 260, true);
$window->setMargined(true);
$window->setChild(
    Build::vbox(
        new Label('Menus are created before the window.'),
        new Label('Every item uses onCLICK() — never raw onCLICKED().'),
    ),
);

// Run the event loop
$window->run();
