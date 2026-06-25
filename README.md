# yangweijie/ui2

**A thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui)** — a native desktop GUI toolkit for PHP powered by `libui-ng` via FFI.

This package adds composite widgets, field helpers, and picker dialogs on top of the upstream's typed widget classes, custom 2D drawing, tables, menus, and dialogs.

## Requirements

- **PHP ≥ 8.5** (`ext-ffi` required)
- Platform library: `libui-ng` (ships prebuilt for macOS, Linux, Windows in the upstream)

> ⚠️ The upstream requires PHP 8.5+. PHP 8.4.x will fail on `composer install`.

## Installation

```bash
composer require yangweijie/ui2
```

The `post-autoload-dump` script automatically applies patches to the upstream vendor files (see [Patch system](#patch-system)).

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
|---|---|---|---|---|
| `TextField` | `Entry` | `string` | Simple text input |
| `SearchField` | `Entry::search()` | `string` | Search-style field; `onChanged` may debounce on macOS |
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

```php
use Yangweijie\Ui2\Dialogs\MessageBox;

MessageBox::info($window, 'Saved', 'Document saved successfully.');
MessageBox::warning($window, 'Disk Space', 'Low disk space.');
MessageBox::error($window, 'Error', 'Could not open file.');
```

### Widgets

Custom-drawn widgets using `Area` + `AreaDelegate`:

| Class | Description |
|---|---|
| `ToggleSwitch` | Area-based toggle switch, emits `'change'` |
| `StatusIndicator` | Colored dot indicator; `setColor()` / `setColorHex()` |

```php
$toggle = new ToggleSwitch(true);
$toggle->on('change', fn (bool $on) => print($on ? 'ON' : 'OFF'));

$status = new StatusIndicator(Color::rgb(0x22C55E)); // green dot
$status->setColorHex(0xEF4444); // red dot
```

### Pickers

Modal dialogs for picking colors and fonts. Both use a **nested event-loop step** (`uiMainStep(1)`) — they do NOT call `uiQuit()`, so they can be called from within an already-running `uiMain()` loop.

| Class | Returns | Description |
|---|---|---|
| `ColorPickerDialog` | `?Color` | Wraps `ColorButton` in a temp modal window. Call: `ColorPickerDialog::pick($window)` |
| `FontPickerDialog` | `?FontDescriptor` | Wraps `FontButton` in a temp modal window. Call: `FontPickerDialog::pick($window)` |

```php
$color = ColorPickerDialog::pick($mainWindow);
if ($color !== null) {
    // Use the selected color
}

$font = FontPickerDialog::pick($mainWindow);
if ($font !== null) {
    // Use the selected font descriptor
}
```

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
| `Window.php` | `centered()` positioning; `getContentSize()`/`getPosition()`; `onClose()`; `run()` single-window loop; menu lock tracking |
| `Exception/MenuOrderException.php` | Carries the Window title that locked menus |
| `Draw/DrawContext.php` | `fillRect`/`strokeRect`/`fillCircle`/`strokeCircle`/`*Arc`/`*RoundedRect`/`*Polygon`/`strokeLine`/`line`/`dot`; `withSave()`; `drawString()` |
| `Draw/Path.php` | `wedge()`/`polygon()`/`ellipse()`/`roundedRect()`/`quadTo()`/`bezierThrough()`; `line()`/`circle()`/`arc()` shorthands |
| `Draw/Params/AreaKeyEvent.php` | Semantic query methods (e.g. `isShiftDown()`) |
| `Draw/Params/AreaMouseEvent.php` | Semantic query methods (e.g. `isLeftButtonDown()`) |

> **Do not edit files inside `vendor/` directly.** Place overrides in `patches/` — they will be mirrored on next install.

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
$path->wedge(100, 100, 50, 0, M_PI_2);  // Pie slice
$path->polygon([10, 50, 90], [10, 90, 10]);  // Triangle
$path->roundedRect(10, 10, 100, 50, 10);  // Rounded corners
$path->bezierThrough([10, 40, 90], [50, 10, 50]);  // Smooth curve through points
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
php examples/menu.php
```

The included example demonstrates both Menu API styles with the patched `MenuItem::onClick()`.

## Upstream essentials

- Always call `Libui\Ffi::init()` before any widget constructor (it is idempotent).
- `Window::run()` = show + event loop + cleanup. For multi-window apps use `Libui\App::run()`.
- Event callbacks return `void`; exceptions are caught and printed to `STDERR`. Always use try/catch in callbacks.
- Closures passed to libui C callbacks are retained by the framework — you do not need to keep references.
- `Window::run()` calls `Ffi::uninit()` in a `finally` block — code after `run()` in the same script runs in a torn-down state. Use the `$afterClose` callback for cleanup.
- `fn () => echo …` is a syntax error in PHP — use `print` or a `function () { … }` body.

## License

MIT
