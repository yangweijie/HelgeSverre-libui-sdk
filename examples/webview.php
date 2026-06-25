<?php
/**
 * Embedded WebView Demo
 *
 * Creates a libui window with native controls in a sidebar and embeds
 * a browser engine (WKWebView / WebKitGTK / WebView2) in the remaining area.
 *
 * Before running:
 *   1. composer install
 *   2. Compile the bridge library for your platform (see bridge/README.md)
 *
 * macOS:     clang -shared -fobjc-arc bridge/webview_bridge.m \
 *               vendor/kingbes/pebview/lib/macos/arm64/PebView.dylib \
 *               -framework Cocoa -o bridge/webview_bridge.dylib
 *
 * Linux:     gcc -shared -fPIC bridge/webview_bridge_linux.c \
 *               $(pkg-config --cflags --libs gtk+-3.0) \
 *               -o bridge/webview_bridge.so
 *
 * Windows:   cl /LD bridge/webview_bridge_win.c user32.lib \
 *               /Fe:bridge/webview_bridge.dll
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\Ffi;
use Libui\Window;
use Libui\Box;
use Libui\Button;
use Libui\Entry;
use Libui\Label;
use Libui\Menu;
use Yangweijie\Ui2\WebView;

// ==========================================================================
// 1. Boot libui
// ==========================================================================
Ffi::init();

// Menus must be created before the first Window.
$appMenu = new Menu('App');
$appMenu->appendQuitItem(); // Cmd+Q

// ==========================================================================
// 2. Create window with layout
// ==========================================================================
$sidebarWidth = 260;
$sidebarMargin = 10;

$win = new Window('libui + Embedded WebView', 1100, 740);
$win->setMargined(true);

// Outer vertical box
$vbox = new Box();

// Horizontal split: sidebar | webview
$hbox = Box::horizontal();

// -- Sidebar (libui native controls) --
$sidebar = new Box();
$sidebar->setPadded(true);

$sidebar->append(new Label('Native Controls'), false);
$sidebar->append(new Button('Click Me'), false);

$entry = new Entry();
$entry->setText('Type PHP text here...');
$sidebar->append($entry, false);

$sidebar->append(new Label(''), true); // spacer

$hbox->append($sidebar, false);

// -- WebView placeholder (empty label the webview will overlap) --
$placeholder = new Label('');
$hbox->append($placeholder, true);

$vbox->append($hbox, true);
$win->setChild($vbox);

$win->show();

// ==========================================================================
// 3. Get content size
// ==========================================================================
[$contentW, $contentH] = $win->getContentSize();

\fwrite(\STDERR, "[demo] Initial content size: {$contentW}x{$contentH}\n");

// ==========================================================================
// 4. Create WebView
// ==========================================================================
$wvW = \max(200, $contentW - $sidebarWidth - $sidebarMargin);
$wvH = \max(200, $contentH);
$wvX = $sidebarWidth + $sidebarMargin;
$wvY = 0;

\fwrite(\STDERR, "[demo] Creating WebView at x={$wvX} y={$wvY} w={$wvW} h={$wvH}\n");

$wv = new WebView($win, $wvX, $wvY, $wvW, $wvH, true); // debug=true enables DevTools

// ==========================================================================
// 5. Set HTML content
// ==========================================================================
$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    padding: 32px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    color: #1a1a2e;
  }
  h1 { font-size: 28px; margin-bottom: 8px; font-weight: 600; }
  .subtitle { color: #555; margin-bottom: 24px; font-size: 14px; }
  .card {
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid rgba(255,255,255,0.5);
  }
  .card p { margin-bottom: 12px; line-height: 1.5; }
  button {
    background: #0071e3;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
  }
  button:hover { background: #0077ed; transform: translateY(-1px); }
  #output {
    margin-top: 12px;
    padding: 12px 16px;
    background: #e8e8ed;
    border-radius: 8px;
    font-family: 'SF Mono', 'Menlo', monospace;
    font-size: 13px;
    min-height: 24px;
    word-break: break-all;
  }
</style>
</head>
<body>
  <div class="card">
    <span class="badge" style="display:inline-block;background:#34c759;color:white;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;margin-bottom:12px;">Embedded WebView</span>
    <h1>Browser Engine inside libui</h1>
    <p class="subtitle">This HTML renders in a native browser view embedded within the libui window</p>
    <button onclick="callPHP()">Call PHP Bridge</button>
    <div id="output">→ Click the button to test the JS↔PHP bridge</div>
  </div>
  <div class="card">
    <h2>How it works</h2>
    <p>1. libui creates the main window with native controls (sidebar)</p>
    <p>2. The bridge creates a borderless child window at the webview area</p>
    <p>3. webview_create() sets the engine inside the child window only</p>
    <p>4. libui's event loop drives everything — no conflicting run loops</p>
    <p>5. On resize, autoResize() repositions the child window</p>
  </div>
  <script>
    function callPHP() {
      var out = document.getElementById('output');
      out.textContent = 'Calling PHP...';
      ping().then(function(result) {
        out.innerHTML = 'PHP response: <strong>' + JSON.stringify(result) + '</strong>';
      }).catch(function(err) {
        out.textContent = 'Error: ' + err;
      });
    }
  </script>
</body>
</html>
HTML;

$wv->setHtml($html);
\fwrite(\STDERR, "[demo] HTML content set\n");

// ==========================================================================
// 6. Bind JS function: ping()
// ==========================================================================
$wv->bind('ping', function (string $id, string $req) use ($wv) {
    try {
        $data = \json_decode($req, true);
        $result = \json_encode([
            'message' => 'Hello from PHP!',
            'timestamp' => \time(),
            'you_sent' => $data,
        ]);
        $wv->return($id, 0, $result);
    } catch (\Throwable $e) {
        \fwrite(\STDERR, "[demo] Error in ping callback: {$e->getMessage()}\n");
    }
});

\fwrite(\STDERR, "[demo] JS function 'ping' bound\n");

// ==========================================================================
// 7. Auto-resize: reposition webview when window is resized
// ==========================================================================
$wv->autoResize($win, $sidebarWidth + $sidebarMargin, 0);

// ==========================================================================
// 8. Handle close — destroy webview before libui tears down
// ==========================================================================
$wv->cleanupOnClose($win);

// ==========================================================================
// 9. App-level quit handler
// ==========================================================================
Ffi::onShouldQuit(fn() => true);

// ==========================================================================
// 10. Run the libui event loop
// ==========================================================================
\fwrite(\STDERR, "[demo] Entering event loop\n");

// Use App::run() instead of Window::run() since we manage lifecycle manually
\Libui\Loop::run();

\fwrite(\STDERR, "[demo] Done.\n");