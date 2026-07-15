<?php
/**
 * Surface + embedded WebView demo (WebViewSpec route).
 *
 * Shows a self-drawn Surface that lays out a header label and a live browser
 * as a leaf node. The WebView is NOT a libui control — it is a native
 * borderless child window that Surface glues to the node's on-screen rect
 * every frame (the same mechanism as the IME text overlay).
 *
 * Before running:
 *   1. composer install
 *   2. Compile the bridge library for your platform (see bridge/README.md)
 *
 * The Window-level generic component (route 1) is demonstrated by
 * {@see \Yangweijie\Ui2\Widgets\WebViewComponent} — see examples/webview.php.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Bypass the Collision handler so any fatal/uncaught error is printed verbatim.
\set_exception_handler(static function (\Throwable $e): void {
    \fwrite(\STDERR, "[uncaught] " . \get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
});
\register_shutdown_function(static function (): void {
    $e = \error_get_last();
    if ($e !== null && \in_array($e['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR], true)) {
        \fwrite(\STDERR, "[shutdown] {$e['message']} in {$e['file']}:{$e['line']}\n");
    }
});

use Libui\Ffi;
use Libui\Window;
use Libui\Build;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Widgets\Surface;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WebViewSpec;

Ffi::init();

// NOTE: libui's menu creation (uiNewMenu) crashes natively under this PHP/FFI
// build, so we intentionally skip the App menu here and quit via the window's
// close button instead — see examples/test-treeview.php for the same pattern.
$win = new Window('Surface + Embedded WebView', 1000, 720);
$win->setMargined(true);
$win->onClosing(function () {
    Ffi::quit();
    return true;
});

$layout = LayoutNode::column(gap: 12, padding: 16)
    ->child(LayoutNode::leaf('hdr', new LabelSpec('Below is a live WebView inside a self-drawn Surface:'), height: 28))
    ->child(LayoutNode::leaf('web', new WebViewSpec(
        url: 'https://example.com',
        debug: false,
    ), height: null))
    ->child(LayoutNode::leaf('footer', new LabelSpec('Resize the window — the browser tracks the node rect.'), height: 24));

$surface = new Surface($layout);

$win->setChild(Build::vbox(Build::stretchy($surface->root())));

$win->show();

// Tear the native browser views down *after* the event loop exits, so we never
// destroy a Cocoa view while the NSApplication run loop is still active.
Ffi::onShouldQuit(fn () => true);
\Libui\Loop::run();

$surface->destroyWebViewOverlays();
