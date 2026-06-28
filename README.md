# yangweijie/ui2

**A thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui)** — a native desktop GUI toolkit for PHP powered by `libui-ng` via FFI.

This package adds composite widgets, field helpers, picker dialogs, custom-drawn widgets, an embedded WebView engine, tree/file browser, code editor, and circular progress bars on top of the upstream's typed widget classes, custom 2D drawing, tables, menus, and dialogs.

## Requirements

- **PHP ≥ 8.5** (`ext-ffi` required)
- Platform library: `libui-ng` (ships prebuilt for macOS, Linux, Windows in the upstream)
- For WebView-based widgets: the PebView native library is downloaded and compiled via `composer install`

> ⚠️ The upstream requires PHP 8.5+. PHP 8.4.x will fail on `composer install`.

## Installation

```bash
composer require yangweijie/ui2
```

The `post-autoload-dump` script automatically:
1. Applies patches to the upstream vendor files (see [Patch system](#patch-system))
2. Builds the native PebView library from source on macOS (requires Xcode CLI tools)

## Quick start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Build;
use Yangweijie\Ui2\Fields\TextField;

Ffi::init();

// A labelled text input with change events
$name = new TextField('Name:', 'World');
$name->on('change', fn (string $val) => print("Hello, {$val}!\n"));

$window = new Window('ui2 Demo', 400, 200);
$window->setMargined(true);
$window->setChild(
    Build::vbox(
        $name,
        new Label('Type in the field above'),
    ),
);
$window->run();
```

## Architecture

### Composite

The core abstraction is `Composite` — an abstract base for widgets built from multiple controls. A `Composite` wraps one or more child controls behind a single `root()` method so the whole group can be added to containers (`Box`, `Form`, `Grid`) as if it were a single widget.

```php
abstract class Composite implements HasValue
{
    abstract public function root(): Control;
    public function value(): mixed { /* override in subclasses */ }
    public function setValue(mixed $value): static { /* override */ }
}
```

All container patches (`Box`, `Form`, `Grid`, `Group`, `Tab`) accept `Composite` children transparently — they call `$composite->root()` internally.

### EmitsEvents

A lightweight event emitter trait. Drop it into any class to add `on(event, handler)` / `emit(event, data)`.

```php
class MyWidget extends Composite
{
    use EmitsEvents;

    public function doSomething(): void
    {
        $this->emit('change', $this->value());
    }
}

$widget->on('change', fn ($val) => print("Changed: {$val}"));
```

All Field composites use this trait and emit `'change'` when the input value changes.

### Fields

Each Field is a `Composite` that pairs a `Label` with a specific input widget in a horizontal row:

| Class | Input widget | Value type | Notes |
|---|---|---|---|
| `TextField` | `Entry` | `string` | Simple text input |
| `SearchField` | `Entry::search()` | `string` | Search-style field; may debounce on macOS |
| `PasswordField` | `Entry::password()` | `string` | Text masked on screen, readable via `value()` |
| `NumberField` | `Spinbox` | `int` | Requires min/max range |
| `SliderField` | `Slider` | `int` | Has live value readout label |
| `FilePickerField` | `Entry` (readonly) + `Button` | `string` | Requires parent `Window`; opens native file dialog |
| `CheckboxField` | `Checkbox` | `bool` | Checkbox with label |
| `RadioGroup` | `RadioButtons` | `int` | Selected index (0-based); `addOptions()` |
| `ComboBoxField` | `Combobox` | `int` | Selected index (0-based); `addOptions()` |
| `EditableComboBoxField` | `EditableCombobox` | `string` | User-typable combo; `addOptions()` |
| `DatePickerField` | `DateTimePicker` | `\DateTimeImmutable` | `dateOnly()`/`timeOnly()` factories |
| `TextAreaField` | `MultilineEntry` | `string` | Vertical label + stretchy text area |
| `ProgressBarField` | `ProgressBar` | (none) | `setProgress()`, `indeterminate()` |
| `SeparatorLine` | `Separator` | (none) | Thin horizontal divider |

```php
$field = new TextField('Name:', 'default');
$field->on('change', fn (string $val) => print($val));
$form->append($field->root(), 'Name:');

// Get/set value
$val = $field->value();
$field->setValue('New value');
```

### Dialogs

| Class | Description |
|---|---|
| `MessageBox` | Static helpers: `info()`, `warning()`, `error()` — wraps upstream native msgBox API |
| `DialogConfirm` | `ask(Window, $title, $message): bool` — modal yes/no dialog |
| `DialogPrompt` | `ask(Window, $title, $label, $default): ?string` — modal text input dialog |

All modal dialogs accept an optional parent Window parameter; when provided, the dialog is centered on the parent window rather than screen-center.

```php
use Yangweijie\Ui2\Dialogs\MessageBox;
use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;

MessageBox::info($window, 'Saved', 'Document saved successfully.');

$confirmed = DialogConfirm::ask($window, 'Delete', 'Are you sure?');
$name = DialogPrompt::ask($window, 'Input', 'Enter your name:', 'John');
```

### Pickers

Modal dialogs for picking values. All use a **nested event-loop step** (`uiMainStep(1)`) — they do NOT call `uiQuit()`, so they can be called from within an already-running `uiMain()` loop. All accept an optional parent Window parameter for centering.

| Class | Returns | Description |
|---|---|---|
| `ColorPickerDialog` | `?Color` | Wraps `ColorButton` in a temp modal window |
| `FontPickerDialog` | `?FontDescriptor` | Wraps `FontButton` in a temp modal window |
| `DatePickerDialog` | `?\DateTimeImmutable` | Date-only picker (no time) |
| `TimePickerDialog` | `?\DateTimeImmutable` | Time-only picker (no date) |

```php
$color = ColorPickerDialog::pick($mainWindow);
if ($color !== null) { /* use color */ }

$font = FontPickerDialog::pick($mainWindow);
if ($font !== null) { /* use font */ }

$date = DatePickerDialog::pick($mainWindow);
$time = TimePickerDialog::pick($mainWindow);
```

### Widgets

#### Custom-drawn (Area-based)

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
$bar->setColor(new Color(0, 0.5, 1));   // blue accent
$bar->setThickness(16);                  // ring width
```

#### Native OS Toast

| Class | Description |
|---|---|
| `Toast` | Static helpers: `show(title, message, ?icon)` — sends native OS desktop notification |

```php
use Yangweijie\Ui2\Widgets\Toast;

Toast::show('ui2', 'File saved successfully!');
Toast::show('Alert', 'Low disk space', '/path/to/icon.png');
```

Only one static method — no instance needed. Works on macOS (Notification Center), Linux (D-Bus), and Windows (Toast API).

### WebView

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

**WebView-based widgets** (also create child windows):

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

`TreeView` supports `expandNode()`, `collapseNode()`, `setData()`, `onNodeClick()`, `onNodeToggle()`.
`CodeEditor` supports `setCode()`, `getCode()`, `setLanguage()`, `onChange()`.

## Patch system

Instead of forking the upstream library, this project overrides specific files via a patch layer:

1. Files in `patches/` mirror the path structure under `vendor/`
2. On `composer install` / `composer update`, the `post-autoload-dump` script (`patch.php`) recursively copies everything from `patches/` into `vendor/`
3. This lets you extend widgets, add methods, or fix behaviour without maintaining a separate fork

**Currently patched files** (under `patches/helgesverre/libui/src/`):

| File | Additions |
|---|---|
| `Box.php` | Accepts `Composite` children; `horizontal()` static factory; `appendStretchy()` |
| `Form.php` | Accepts `Composite` children; `values()`/`setValues()` for HasValue fields; `appendStretchy()` |
| `Grid.php` | Accepts `Composite` children; `appendAt()` positional args; `place()` shortcut |
| `Group.php` | Accepts `Composite` children; `titled()` static factory |
| `Tab.php` | Accepts `Composite` children in `append()`/`appendMargined()` |
| `Menu.php` | Fluent builder API (`create()->item()->separator()->quitItem()`); improved `MenuOrderException` |
| `MenuItem.php` | `onClick()` replaces handler (no C trampoline stacking); `removeOnClick()`; per-call & global error handlers |
| `Window.php` | `centered()` positioning; `centeredOn()` parent-relative centering; `getContentSize()`/`getPosition()`; `onClose()`; `run()` single-window loop; menu lock tracking; `setWindowIcon()` cross-platform dock/taskbar icon |
| `Exception/MenuOrderException.php` | Carries the Window title that locked menus |
| `Draw/DrawContext.php` | `fillRect`/`strokeRect`/`fillCircle`/`strokeCircle`/`*Arc`/`*RoundedRect`/`*Polygon`/`strokeLine`/`line`/`dot`; `withSave()`; `drawString()` |
| `Draw/Path.php` | `wedge()`/`polygon()`/`ellipse()`/`roundedRect()`/`quadTo()`/`bezierThrough()`; `line()`/`circle()`/`arc()` shorthands |
| `Draw/Params/AreaKeyEvent.php` | Semantic query methods (e.g. `isShiftDown()`) |
| `Draw/Params/AreaMouseEvent.php` | Semantic query methods (e.g. `isLeftButtonDown()`) |

> **Do not edit files inside `vendor/` directly.** Place overrides in `patches/` — they will be mirrored on next install.

## Bridge system

The `bridge/` directory contains platform-specific C source files that connect PHP to native WebView APIs:

| Platform | Source | Binary |
|---|---|---|
| macOS | `bridge/webview_bridge.m` (Objective-C) | `webview_bridge.dylib` |
| Linux | `bridge/webview_bridge_linux.c` | `webview_bridge.so` |
| Windows | `bridge/webview_bridge_win.c` | `webview_bridge.dll` |

The bridge library is loaded via FFI and handles creating, moving, and destroying the child window that hosts the native WebView.

## Drawing

The patched `DrawContext` provides a fluent builder pattern for 2D drawing:

```php
$context->fillRect(10, 10, 100, 50, $brush);
$context->strokeCircle(60, 80, 30, $strokeParams);
$context->fillPolygon([10, 20, 30], [10, 40, 10], $brush);

// Save/restore transform state
$context->withSave(function (DrawContext $ctx) {
    $ctx->translate(50, 50);
    $ctx->fillRect(0, 0, 20, 20, $brush);
});

// Measure and draw text
$context->drawString('Hello', 10, 10, $font, $brush);
```

The patched `Path` adds convenience methods:

```php
$path->wedge(100, 100, 50, 0, M_PI_2);          // Pie slice
$path->polygon([10, 50, 90], [10, 90, 10]);     // Triangle
$path->roundedRect(10, 10, 100, 50, 10);        // Rounded corners
$path->bezierThrough([10, 40, 90], [50, 10, 50]); // Smooth curve
```

## Menus

Two APIs coexist. Menus **must** be created before the first `Window` (enforced at runtime via `MenuOrderException`):

### Declarative / fluent style

```php
Menu::create('File')
    ->item('Open', fn (MenuItem $item) => /* ... */)
    ->separator()
    ->quitItem();

Menu::create('Edit')
    ->checkItem('Dark Mode', fn (MenuItem $item) => /* ... */);
```

### Imperative style

```php
$help = new Menu('Help');
$about = $help->appendAboutItem();
$about->onClick(fn (MenuItem $item) => /* ... */);
```

> **Note:** The patched `MenuItem::onClick()` replaces the handler on each call — it does NOT stack like most libui callbacks.

## Running tests

```bash
vendor/bin/pest
```

The project uses Pest 4 (built on PHPUnit 12). The existing `tests/DialogsTest.php` tests an upstream private method via reflection — no FFI needed.

## Examples

```bash
php examples/all-components.php   # Full demo with 6 tabs showing all widgets
php examples/menu.php              # Declarative vs imperative menu APIs
php examples/webview.php           # WebView with sidebar, JS ↔ PHP bridge
```

The `all-components.php` example demonstrates every widget in this package across 6 tabs:
1. **Fields** — all input field types
2. **Custom** — ToggleSwitch, StatusIndicator, CircleProgressBar
3. **Dialogs** — MessageBox, DialogConfirm, DialogPrompt, Toast
4. **Pickers** — Color, Font, Date, Time pickers
5. **Table** — Tabular data with TableView
6. **WebView** — TreeView and CodeEditor launch buttons

## Upstream essentials

- Always call `Libui\Ffi::init()` before any widget constructor (it is idempotent).
- `Window::run()` = show + event loop + cleanup. For multi-window apps use `Libui\App::run()`.
- **`Window::setWindowIcon(string $iconPath)`** — set dock/taskbar icon. macOS→bridge dylib (`NSApp setApplicationIconImage:`); Linux/Windows→PebView `set_icon()`.
- **`App::afterInit(\Closure $callback)`** — queue a callback that runs right after `Ffi::init()` but before the event loop. Useful for setting dock icon at startup.
- Event callbacks return `void`; exceptions are caught and printed to `STDERR`. Always use try/catch in callbacks.
- Closures passed to libui C callbacks are retained by the framework — you do not need to keep references.
- `Window::run()` calls `Ffi::uninit()` in a `finally` block — code after `run()` in the same script runs in a torn-down state. Use the `$afterClose` callback for cleanup.
- `fn () => echo …` is a syntax error in PHP — use `print` or a `function () { … }` body.
- **WebView widgets are not** `Composite` objects. They create borderless child windows at absolute coordinates. They cannot be placed inside `Box`, `Form`, or `Tab` layouts. Use `autoResize()` to keep them positioned correctly when the parent window resizes.

## App icon

Set the dock/taskbar icon from a PNG file at any time:

```php
use Libui\Window;

$window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
```

To set the icon immediately at startup (before the event loop draws the window), use `App::afterInit()`:

```php
use Libui\App;
use Libui\Ffi;
use Libui\Window;

Ffi::init();

$window = new Window('My App', 600, 400);

App::afterInit(function () use ($window): void {
    $window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
});

$window->run();
```

macOS uses `NSApp setApplicationIconImage:` via the bridge dylib; Linux and Windows use PebView's `set_icon()`.

## License

MIT
