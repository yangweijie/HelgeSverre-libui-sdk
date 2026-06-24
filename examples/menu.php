<?php

declare(strict_types=1);

/**
 * Menu demo — demonstrates both the declarative fluent builder and the
 * imperative Menu API with the patched MenuItem::onClick().
 *
 * Run: php examples/menu.php
 *
 * Key points:
 *
 * - DECLARATIVE STYLE: Menu::create('File')->item('Open', fn)...->quitItem()
 *   returns the Menu for chaining; see the File and Edit menus below.
 * - IMPERATIVE STYLE: new Menu('File') followed by appendItem()/appendCheckItem()
 *   returns the MenuItem for later manipulation; see Help menu.
 * - Every click handler uses onCLICK() (the patched wrapper), never the raw
 *   onCLICKED(). onClick() hides libui's raw uiWindow* parameter and accepts
 *   an optional per-call error handler.
 * - Check items are togglable via checked()/setChecked().
 * - Menus MUST be created before any Window exists (enforced at runtime by
 *   MenuOrderException, which now tells you which Window locked the menu).
 *
 * @see patches/helgesverre/libui/src/Menu.php
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

// --- File menu (declarative / fluent style) ---
Menu::create('File')
    ->item('Open', function (MenuItem $item) use (&$window): void {
        $window->dialogs()->msgBox('Open', 'You clicked Open (demo only)');
    })
    ->separator()
    ->quitItem();   // platform Quit — no click handler, default behaviour

// --- Edit menu (fluent, mix of item and checkItem) ---
// When you need the MenuItem reference later, capture it from the callback or
// switch to the imperative append*() methods.
Menu::create('Edit')
    ->item('Cut', function (MenuItem $item) use (&$window): void {
        $window->dialogs()->msgBox('Cut', 'Cut clicked (demo only)');
    })
    ->checkItem('Copy', function (MenuItem $item) use (&$window): void {
        $checked = $item->checked() ? 'checked' : 'unchecked';
        $window->dialogs()->msgBox('Copy', "Copy is now {$checked}");
    })
    ->item('Paste', function (MenuItem $item) use (&$window): void {
        $window->dialogs()->msgBox('Paste', 'Paste clicked (demo only)');
    });

// --- Help menu (imperative style — keep MenuItem for later use) ---
$helpAbout = new Menu('Help');

// appendAboutItem() returns the MenuItem, so we can keep a reference.
$aboutItem = $helpAbout->appendAboutItem();
$aboutItem->onClick(function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('About', 'Menu Demo — fluent builder + patched onClick()');
});

// You can also keep a reference to any appendItem result:
$helpItem = $helpAbout->appendItem('Help Contents');
$helpItem->onClick(function (MenuItem $item) use (&$window): void {
    $window->dialogs()->msgBox('Help', 'Help Contents (not yet implemented)');
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
