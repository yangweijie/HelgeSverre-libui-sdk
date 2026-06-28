# WebView

在 libui 窗口内以无边框子窗口的形式嵌入原生浏览器引擎（macOS 上为 WKWebView，Linux 上为 WebKitGTK，Windows 上为 WebView2）。这不是 Composite——它会在绝对坐标位置创建一个**覆盖**子窗口。

```php
use Yangweijie\Ui2\WebView;

$webview = new WebView($window, $x, $y, $width, $height, $debug);
$webview->navigate('https://example.com');
$webview->setHtml('<h1>你好</h1>');

// JS ↔ PHP 桥接
$webview->bind('ping', function (string $id, string $req) use ($webview) {
    $webview->return($id, 0, json_encode(['ok' => true]));
});
$webview->eval('ping("你好")');

// 跟随窗口自动调整大小
$webview->autoResize($window, $sidebarWidth, $topMargin);
```

## 重要提示

- **WebView 控件不是 `Composite`**——它们在绝对坐标位置创建无边框子窗口
- 不能放入 `Box`、`Form` 或 `Tab` 布局中
- 使用 `autoResize()` 在父窗口调整大小时保持正确定位
- bridge/ 目录包含用于创建、移动和销毁子窗口的平台 C 源码
