# WebView

Embeds a native browser engine (WKWebView on macOS, WebKitGTK on Linux, WebView2 on Windows) inside a libui Window as a borderless child window. This is not a Composite — it creates an **overlay** child window at absolute coordinates.

```php
use Yangweijie\Ui2\WebView;

$webview = new WebView($window, $x, $y, $width, $height, $debug);
$webview->navigate('https://example.com');
$webview->setHtml('<h1>Hello</h1>');

// JS ↔ PHP bridge
$webview->bind('ping', function (string $id, string $req) use ($webview) {
    $webview->return($id, 0, json_encode(['ok' => true]));
});
$webview->eval('ping("hello")');

// Auto-resize with window
$webview->autoResize($window, $sidebarWidth, $topMargin);
```

## Important Notes

- **WebView widgets are NOT `Composite`** — they create borderless child windows at absolute coordinates
- Cannot be placed inside `Box`, `Form`, or `Tab` layouts
- Use `autoResize()` to keep positioned correctly when parent window resizes
- The bridge directory has platform C source for creating/moving/destroying the child window
