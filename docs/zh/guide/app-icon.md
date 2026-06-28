# 应用图标

通过 PNG 文件设置 Dock/任务栏图标：

```php
$window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
```

## 启动时设置

要在启动时立即设置图标（在事件循环绘制窗口之前），使用 `App::afterInit()`：

```php
use Libui\App;
use Libui\Ffi;
use Libui\Window;

Ffi::init();
$window = new Window('我的应用', 600, 400);

App::afterInit(function () use ($window): void {
    $window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
});

$window->run();
```

## 平台细节

- **macOS**：通过桥接 dylib 调用 `NSApp setApplicationIconImage:`
- **Linux**：使用 PebView `set_icon()`
- **Windows**：使用 PebView `set_icon()`
