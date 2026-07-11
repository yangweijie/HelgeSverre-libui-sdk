# Widgets

## Custom-Drawn (Area-based)

| Class | Description |
|---|---|
| `ToggleSwitch` | Area-based toggle switch; `on('change')` emits `bool` |
| `StatusIndicator` | Colored dot indicator; `setColor()` / `setColorHex()` |
| `CircleProgressBar` | Circular / ring-style progress bar; `setProgress()`, `setColor()`, `setThickness()` — now uses DesignTokens theme system |
| `TableView` | Wraps upstream `Table` with typed columns and data binding |
| **`SvgView`** | SVG rendering widget; parses SVG path data and draws via `Area` — supports rectangles, circles, ellipses, lines, polylines, polygons, arcs, and cubic/quadratic beziers |
| **`SvgDelegate`** | AreaDelegate extracted from `SvgView` — handles SVG parsing, hit-testing, mouse events (hover highlight, click) |

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

## Surface Canvas Widget

`Surface` is a **composable canvas widget** (1056 lines) built on a single libui `Area`. It combines the Layout engine, Rendering engine, WidgetRenderer registry, and Event system into one embeddable widget that can be placed in any libui container (`Box`, `Form`, `Grid`, `Tab`).

| Class | Description |
|---|---|
| `Surface` | High-level composable canvas: internal `FlexLayout` + `RendererFactory` + command caching + mouse/keyboard routing |

```php
use Yangweijie\Ui2\Widgets\Surface;

$surface = new Surface(400, 300);
$surface->addChild('button1', ButtonRenderer::class, ['label' => 'Click me']);
$surface->addChild('slider1', SliderRenderer::class, ['min' => 0, 'max' => 100, 'value' => 50]);

$container = new Box(Box::Vertical);
$container->append($surface);
```

`Surface` widgets are **fully composable** with libui containers — they are not child-window-based (unlike WebView widgets).

### Surface-based Controls

These full-featured controls are built on `Surface` and `WidgetRenderer`:

| Class | Description |
|---|---|
| `ButtonControl` | Surface-rendered button with hover/active states |
| `CheckboxControl` | Custom checkbox with label |
| `RadioControl` | Radio button with hover/active states and fill animation |
| `SliderControl` | Horizontal slider with drag handle |
| `ProgressControl` | Progress bar with determinate fill |
| `TextFieldControl` | Text input with cursor and selection |
| `SelectControl` | Dropdown-style select |
| `ComboboxControl` | Searchable combo box |
| `BreadcrumbControl` | Navigation breadcrumb trail |
| `DialogControl` | Modal dialog container |
| `DrawerControl` | Side drawer panel |
| `DropdownMenuControl` | Dropdown menu |
| `ListControl` | Scrollable item list |
| `PaginationControl` | Page navigation |
| `PopoverControl` | Popover tooltip |
| `ScrollViewControl` | Scrollable content area |
| `SearchFieldControl` | Search input with icon |
| `SheetControl` | Bottom sheet panel |
| `TabControl` | Tab switcher |
| `TableControl` | Data table with headers |
| `TextAreaControl` | Multi-line text area |

See [Architecture](/en/guide/architecture) for details on how Surface, the Rendering engine, and the Layout engine work together.

## RendererButton (Bridge Widget)

`RendererButton` is a **bridge** between libui's native `Button` and the custom WidgetRenderer system. It extends `Composite`, wraps a real libui `Button`, but renders its appearance via a `ButtonRenderer` using the Surface rendering pipeline.

| Class | Description |
|---|---|
| `RendererButton` | Composite widget — native libui Button underneath, custom-drawn appearance via ButtonRenderer + DesignTokens |

```php
use Yangweijie\Ui2\Widgets\RendererButton;

$btn = new RendererButton('Themed Button', function () {
    print('Clicked!');
});
$container->append($btn);
```

## SVG Rendering

### SvgView

`SvgView` renders SVG path data directly onto an `Area` — no external SVG library required. It parses `<path d="..." />`, `<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`, and common transform attributes, converting them into native libui draw operations.

```php
use Yangweijie\Ui2\Widgets\SvgView;

$svg = new SvgView(
    'M10 10 L 100 10 L 100 80 Z',  // SVG path data
    120, 100,                         // viewport width, height
    ['fill' => '#3B82F6', 'stroke' => '#1D4ED8', 'stroke-width' => 2]
);
$container->append($svg);
```

### SvgDelegate

`SvgDelegate` (`src/Widgets/SvgDelegate.php`) is the `AreaDelegate` implementation extracted from `SvgView`. It provides:

- **SVG parsing** — supports `M`, `L`, `H`, `V`, `C`, `Q`, `A`, `Z` path commands, plus `<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`
- **Hit-testing** — exact geometric hit-test (circle: `dx²+dy² ≤ r²`, ellipse: `(dx/rx)² + (dy/ry)² ≤ 1`)
- **Mouse interaction** — hover highlight via `EmitsEvents` trait, click detection
- **Arc conversion** — endpoint to center parameterization for SVG arc commands

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
