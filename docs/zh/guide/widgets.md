# 自定义控件

## 自绘控件（基于 Area）

| 类名 | 说明 |
|---|---|
| `ToggleSwitch` | 基于 Area 的开关控件；`on('change')` 发射 `bool` |
| `StatusIndicator` | 彩色圆点指示器；`setColor()` / `setColorHex()` |
| `CircleProgressBar` | 环形进度条；`setProgress()`、`setColor()`、`setThickness()` |
| `TableView` | 封装上游 `Table`，支持类型化列和数据绑定 |

```php
$toggle = new ToggleSwitch(true);
$toggle->on('change', fn (bool $on) => print($on ? '开' : '关'));

$status = new StatusIndicator(new Color(0x22, 0xC5, 0x5E));
$status->setColorHex(0xEF4444);

$bar = new CircleProgressBar(50);
$bar->setProgress(75);
$bar->setColor(new Color(0, 0.5, 1));
$bar->setThickness(16);
```

## 原生 OS 通知

| 类名 | 说明 |
|---|---|
| `Toast` | 静态助手：`show(title, message, ?icon)` — 发送原生 OS 桌面通知 |

```php
use Yangweijie\Ui2\Widgets\Toast;

Toast::show('ui2', '文件保存成功！');
Toast::show('警告', '磁盘空间不足', '/path/to/icon.png');
```

仅一个静态方法——无需实例化。支持 macOS（通知中心）、Linux（D-Bus）和 Windows（Toast API）。

## WebView 控件

这些控件继承 `WebView`，创建无边框子窗口（参见 [WebView](/zh/guide/webview)）：

| 类名 | 说明 |
|---|---|
| `TreeView` | 可折叠的文件/对象树，支持图标、点击和切换回调 |
| `CodeEditor` | 基于 highlight.js 的代码编辑器，支持 17 种语言语法高亮 |

```php
$tree = new TreeView($window, 0, 0, 260, 400, [
    ['label' => 'src', 'icon' => 'folder', 'children' => [
        ['label' => 'index.php', 'icon' => 'code'],
        ['label' => 'style.css', 'icon' => 'file'],
    ]],
]);
$tree->onNodeClick(fn (string $path, array $node) => print("点击: {$path}"));

$editor = new CodeEditor($window, 0, 0, 600, 400, 'php', false,
    "<?php\n\necho 'hello';\n"
);
$editor->onChange(fn (string $code) => print("编辑器变更: {$code}"));
```
