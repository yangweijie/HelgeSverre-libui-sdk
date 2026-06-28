# Architecture & Patterns

## Project Structure

```
src/
├── Composite.php              # Abstract base for multi-control widgets
├── EmitsEvents.php            # Event emitter trait (on/emit)
├── WebView.php                # Embedded browser (WKWebView/WebKitGTK/WebView2)
├── System/                    # System integration
│   ├── Tray.php               # System tray icon
│   ├── GlobalHotkey.php       # Global keyboard shortcuts
│   ├── SystemInfo.php         # CPU, memory, architecture
│   └── ProcessUtil.php        # Process utilities
├── Fields/                    # Form fields (18 widgets)
├── Pickers/                   # Modal picker dialogs (4)
├── Dialogs/                   # Message boxes (3)
├── Widgets/                   # Custom-drawn widgets (8)
├── Layout/                    # Layout containers (2)
└── Logging/                   # Logging utilities
```

## Core Patterns

### Composite Pattern

All multi-control widgets extend `Composite`:

```php
abstract class Composite {
    abstract public function root(): Control;
}
```

**Usage**: Pass a `Composite` where a `Control` is expected. Patched upstream containers (`Box`, `Form`, `Grid`, `Group`, `Tab`) call `->root()` internally.

```php
$tab = new TabContainer([
    'Fields' => new TextField('Name:'),  // Composite passed directly
]);
$window->setChild($tab->root());
```

### Patch System (Append-Only)

Extend upstream classes without forking:

```
patches/helgesverre/libui/src/
├── Box.php          # Composite children, horizontal(), appendStretchy()
├── Form.php         # Composite children, values()/setValues(), appendStretchy()
├── Grid.php         # Composite children, appendAt(), place()
├── Group.php        # Composite children, titled()
├── Tab.php          # Composite children
├── Menu.php         # Fluent builder: create()->item()->separator()->quitItem()
├── MenuItem.php     # onClick() REPLACES handler (no stacking)
├── Window.php       # centered(), onClose(), run() loop, menu lock tracking
├── DrawContext.php  # Builder: fillRect, strokeCircle, withSave(), drawString()
├── Path.php         # wedge(), polygon(), ellipse(), roundedRect(), quadTo(), bezierThrough()
└── Area*Event.php   # Semantic query methods
```

**How it works**: `composer install` runs `patch.php` which recursively copies `patches/` → `vendor/`. Stale patches are never auto-removed.

### Event System

```php
trait EmitsEvents {
    public function on(string $event, callable $handler): void;
    public function emit(string $event, mixed $data = null): void;
}
```

All widgets use this for callbacks (onClick, onChange, onSelect, etc.).

### FFI Initialization

```php
Libui\Ffi::init();  // Idempotent - call before any widget creation
```

**Critical**: `Window::run()` calls `Ffi::uninit()` in `finally`. Code after `run()` runs in torn-down state. For multi-window apps, use `Libui\App::run()` instead (does not tear down FFI).

### Menu Ordering (Enforced at Runtime)

**Menus must be created before the first Window**. Violating this throws `MenuOrderException` (carries the window title that locked menus).

```php
// ✅ Correct order
$menu = Menu::create('File')->item('Quit', fn() => App::quit());
$window = new Window('App', 400, 300);
$window->setMenu($menu);

// ❌ Wrong - throws MenuOrderException
$window = new Window('App', 400, 300);
$menu = Menu::create('File');  // FAILS
```

## Data Structures

### Widget Hierarchy

```
Control (upstream base)
├── Window
├── Box / Form / Grid / Group / Tab (patched for Composite)
├── Area (custom drawing)
├── Entry / MultilineEntry / Combobox / Checkbox / RadioButtons / Slider / ProgressBar / ColorButton / FontButton / DateTimePicker / Separator / Label / Button
└── Menu / MenuItem
```

### WebView Architecture

```
libui Window (parent_hwnd)
└─ Bridge STATIC child (child_hwnd)
   └─ PebView webview_widget (initial 0×0)
        └─ WebView2 / WKWebView / WebKitGTK controller
```

**Key insight**: `webview_widget` starts at 0×0. Needs `WM_SIZE` to trigger `resize_widget()`. Bridge handles this via `FindWindowExW` + `MoveWindow` + `SendMessage(WM_SIZE)`.

### JS ↔ PHP Bridge

| Platform | JS → PHP |
|----------|----------|
| macOS | `window.webkit.messageHandlers.__webview__.postMessage(msg)` |
| Windows | `window.chrome.webview.postMessage(msg)` |
| Linux | `window.chrome.webview.postMessage(msg)` |

PHP → JS: `$webView->evaluateScript($jsCode)`

## Upstream Essentials (from helgesverre/libui)

- **`Libui\Ffi::init()`** — Idempotent, call before any widget
- **`Libui\Ffi::get()`** — Lazy-loads C header + native lib, singleton `\FFI`
- **`Libui\Ffi::$retained`** — Closures retained automatically, no manual references needed
- **`Libui\App::afterInit(\Closure $cb)`** — Queue callback after `Ffi::init()` but before event loop
- **`Window::setWindowIcon(string $path)`** — Cross-platform dock/taskbar icon
- **Event callbacks** — Return `void`, exceptions caught and printed to STDERR
- **`fn () => echo ...`** — **Syntax error in PHP**, use `print` or `function () {}`

## Key Differences from Upstream

| Feature | Upstream (libui) | ui2 |
|---------|------------------|-----|
| Container children | Control only | **Composite + Control** (via patches) |
| Menu API | Imperative only | **Declarative + Imperative** |
| MenuItem.onClick | Stacks handlers | **Replaces** handler |
| Window.run() | Basic loop | **Menu lock tracking, centered(), onClose()** |
| DrawContext | Basic methods | **Builder pattern, withSave(), drawString()** |
| Path | Basic shapes | **wedge, polygon, ellipse, roundedRect, bezierThrough** |
| Area events | Raw params | **Semantic query methods** |

## Gotchas

1. **Composite GC Trap**: Temporary composites like `(new SeparatorLine())->root()` get `__destruct()` called at statement end, destroying C widget while libui still holds reference. **Always store in named variables.**

2. **WebView is NOT Composite**: Creates borderless child window at absolute coordinates. Cannot go in Box/Form/Tab. Use `autoResize()` to track parent.

3. **Two Menu APIs Coexist**: Declarative (`Menu::create()->item()`) and imperative (`new Menu()`). Both valid. Imperative needed when you need the `MenuItem` reference.

4. **Windows DLL Paths**: `vendor/kingbes/pebview/lib/windows/PebView.dll` (NOT `windows/x86_64/`)

5. **HWND Conversion**: `$window->handle()` returns `uiWindow*`, NOT Win32 HWND. Use `Ffi::get()->uiControlHandle($window->asControl())` for real HWND.