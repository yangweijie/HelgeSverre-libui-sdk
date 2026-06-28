# Widgets

## Custom-Drawn (Area-based)

| Class | Description |
|---|---|
| `ToggleSwitch` | Area-based toggle switch; `on('change')` emits `bool` |
| `StatusIndicator` | Colored dot indicator; `setColor()` / `setColorHex()` |
| `CircleProgressBar` | Circular / ring-style progress bar; `setProgress()`, `setColor()`, `setThickness()` |
| `TableView` | Wraps upstream `Table` with typed columns and data binding |

```php
$toggle = new ToggleSwitch(true);
$toggle->on('change', fn (bool $on) => print($on ? 'ON' : 'OFF'));

$status = new StatusIndicator(new Color(0x22, 0xC5, 0x5E));
$status->setColorHex(0xEF4444);

$bar = new CircleProgressBar(50);
$bar->setProgress(75);
$bar->setColor(new Color(0, 0.5, 1));
$bar->setThickness(16);
```

## Native OS Toast

| Class | Description |
|---|---|
| `Toast` | Static helpers: `show(title, message, ?icon)` — sends native OS desktop notification |

```php
use Yangweijie\Ui2\Widgets\Toast;

Toast::show('ui2', 'File saved successfully!');
Toast::show('Alert', 'Low disk space', '/path/to/icon.png');
```

Only one static method — no instance needed. Works on macOS (Notification Center), Linux (D-Bus), and Windows (Toast API).

## WebView-based Widgets

These extend `WebView` and create borderless child windows (see [WebView](/en/guide/webview)):

| Class | Description |
|---|---|
| `TreeView` | Collapsible file/object tree with icons, click and toggle callbacks |
| `CodeEditor` | Code editor with syntax highlighting via highlight.js (17 languages) |

```php
$tree = new TreeView($window, 0, 0, 260, 400, [
    ['label' => 'src', 'icon' => 'folder', 'children' => [
        ['label' => 'index.php', 'icon' => 'code'],
        ['label' => 'style.css', 'icon' => 'file'],
    ]],
]);
$tree->onNodeClick(fn (string $path, array $node) => print("Clicked: {$path}"));

$editor = new CodeEditor($window, 0, 0, 600, 400, 'php', false,
    "<?php\n\necho 'hello';\n"
);
$editor->onChange(fn (string $code) => print("Editor changed: {$code}"));
```
