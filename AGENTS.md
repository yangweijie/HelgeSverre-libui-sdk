# AGENTS.md — yangweijie/ui2

Thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui) — native desktop GUI for PHP 8.5+ via FFI. Adds composite widgets, fields, pickers, dialogs, custom-drawn widgets, WebView, tree/file browser, code editor, and circular progress bars.

## Project structure

| Path | Purpose |
|---|---|
| `src/` | **Your code** — `Yangweijie\Ui2\` namespace |
| `src/Composite.php` | Abstract base for multi-control widgets. Subclass, override `root()` |
| `src/EmitsEvents.php` | Trait: `on(event, handler)` / `emit(event, data)` |
| `src/Fields/` | Label + input combos (TextField, NumberField, CheckboxField, etc.) |
| `src/Pickers/` | Modal picker dialogs (Color, Font, DatePicker, TimePicker) |
| `src/Dialogs/` | MessageBox, DialogConfirm, DialogPrompt |
| `src/Widgets/` | Custom-drawn: ToggleSwitch, StatusIndicator, CircleProgressBar, Toast, TableView, TreeView, CodeEditor |
| `src/Layout/` | TabContainer, GroupSection — convenience wrappers |
| `src/WebView.php` | Embedded browser (WKWebView/WebKitGTK/WebView2) via borderless child window |
| `assets/` | HTML/JS assets for WebView-based widgets (tree-view.html, code-editor.html) |
| `patches/` | Files copied into `vendor/` on install (patch system). **Append-only** — stale patches are never removed. |
| `bridge/` | C/ObjC source for WebView child-window bridge per platform |
| `bootstrap.php` | Auto-loaded via `composer.json autoload.files` — registers Collision error handler |
| `vendor/helgesverre/libui/` | Upstream. **Never edit directly** — use `patches/` |
| `tests/` | Pest tests |
| `examples/` | Runnable demos |

## Patch system

`composer install` runs `patch.php`, which recursively copies `patches/` into `vendor/`. The `patches/` directory mirrors `vendor/` structure. This lets you override upstream files without forking.

**Append-only** — stale patches are never removed. If you delete a file from `patches/`, the previous copy still lives in `vendor/`. Clean it manually.

**Currently patched** (under `patches/helgesverre/libui/src/`):

- `Box.php` — Composite children; `horizontal()` factory; `appendStretchy()`
- `Form.php` — Composite children; `values()`/`setValues()`; `appendStretchy()`
- `Grid.php` — Composite children; `appendAt()`; `place()` shortcut
- `Group.php` — Composite children; `titled()` factory
- `Tab.php` — Composite children
- `Menu.php` — Fluent builder: `create()->item()->separator()->quitItem()`
- `MenuItem.php` — `onClick()` **replaces** handler (no stacking); `removeOnClick()`
- `Window.php` — `centered()`/`centeredOn()`; `onClose()`; `run()` loop; menu lock tracking
- `Exception/MenuOrderException.php` — carries the Window title that locked menus
- `Draw/DrawContext.php` — Builder pattern: `fillRect`/`strokeCircle`/`withSave()`/`drawString()`
- `Draw/Path.php` — `wedge()`/`polygon()`/`ellipse()`/`roundedRect()`/`quadTo()`/`bezierThrough()`
- `Draw/Params/Area{Key,Mouse}Event.php` — Semantic query methods

## Build commands

```bash
composer install              # runs patch.php via post-autoload-dump
composer build:pebview        # compiles PebView.dylib from source (macOS/Linux)
composer build:bridge         # compiles webview_bridge.dylib after PebView is ready
php patch.php                 # manually re-apply patches (vendor-mirrored file copy)
```

## Widget catalog (src/Widgets/)

| Widget | Approach | Key behaviour |
|---|---|---|
| `CircleProgressBar` | `Area` + `DrawContext` builder | `setProgress(int)` — ring arc fill/stroke. NOT a WebView widget. |
| `Toast` | Static FFI to PebView `Toast.dylib` | `Toast::show(title, message, icon?)` — native OS notification. No instance needed. |
| `ToggleSwitch` | `Composite` wrapping libui controls | ON/OFF toggle with callback |
| `StatusIndicator` | `Area` with colored circle draw | On/off/dim states |
| `TableView` | libui `MultilineEntry` wrapper | Tabular read-only text display |
| `TreeView` | Extends `WebView` | `setData()`, `expandNode()`, `onNodeClick()` |
| `CodeEditor` | Extends `WebView` + highlight.js | `setCode()`, `setLanguage()`, `onChange()` |

## WebView and bridge

`WebView` creates a **borderless child window** at absolute coordinates — it is NOT a `Composite` and cannot go in `Box`/`Form`/`Tab` layouts. Use `autoResize()` to track parent window resizing.

`TreeView` and `CodeEditor` extend `WebView` — they also create child windows.

The `bridge/` directory has platform C source. Compiled binaries are in `bridge/*.dylib|*.so|*.dll` (gitignored). Compilation instructions are in `bridge/README.md`. Requires the PebView native library from `vendor/kingbes/pebview/`.

## Upstream essentials

- **`Libui\Ffi::init()`** — call before any widget. Idempotent.
- **Menus before first Window** — enforced at runtime (`MenuOrderException`).
- **`Window::run()`** = show + event loop + `Ffi::uninit()` in `finally`. Code after `run()` in the same script runs in a torn-down state — use the `$afterClose` callback. For multi-window apps, use `Libui\App::run()` instead (does not tear down FFI).
- **`Window::setWindowIcon(string $iconPath)`** — set dock/taskbar icon cross-platform. macOS→bridge dylib via `NSApp setApplicationIconImage:`; Linux/Windows→PebView `set_icon()`.
- **`App::afterInit(\Closure $callback)`** — queue callback to run right after `Ffi::init()` but before event loop. E.g. for setting dock icon at startup.
- **Event callbacks** return `void`. Exceptions caught and printed to `STDERR`. Use try/catch in callbacks.
- **Closures retained** by `Ffi::$retained` — no need to keep references yourself.
- **`fn () => echo …`** is a PHP syntax error — use `print` or `function () {}`.
- **`Ffi::get()`** lazily loads the C header and native lib. Singleton `\FFI` handle.
- **Generated code** in `src/Native/` and `src/Generated/` — never edit by hand.

## Testing

```bash
vendor/bin/pest
```

Pest 4 (PHPUnit 12). `phpunit.xml` exists with a `yangweijie/ui2` suite.

- `tests/Pest.php` is the Pest config (standard).
- `Libui\Testing\CallbackSpy` and `Libui\Testing\Inspect` available for assertion-based tests without an event loop.
- `Window::resetMenuLock()` available for tests that need a Menu after a Window.
- `DialogsTest.php` tests a private upstream method via reflection — no FFI needed.
- Write new tests in Pest style (`test()`/`it()`, `expect()`).

## Upstream composer commands

Run from `vendor/helgesverre/libui/`:

```
composer test         # PHPUnit suite
composer stan         # PHPStan level 6
composer format       # Mago formatter
composer regen        # Regenerate FFI header + typed classes from ui.h
```

## Requirements

- PHP ≥ 8.5 (`ext-ffi` required)
- `libui-ng` ships prebuilt for macOS/Linux/Windows in the upstream
- PebView native library built via `composer install`

## Common agent pitfalls

- **Never edit `vendor/` directly** — place overrides in `patches/` (mirrored on install).
- **Never create PHP files using `Libui\` classes without `require 'vendor/autoload.php'`**.
- **Two Menu APIs coexist**: declarative (`Menu::create('File')->item(...)`) and imperative (`new Menu('File')`). Both valid. Imperative needed when you need the `MenuItem` reference.
- **Patched `MenuItem::onClick()` replaces** the handler — does NOT stack. Differs from most libui callbacks.
- **`Window::run()` calls `Ffi::uninit()` in `finally`** — code after `run()` in the same script is in a torn-down state.
- **Container patches (Box, Form, Grid, Group, Tab) accept `Composite`** — pass a `Composite` where you'd pass a `Control`. Patch calls `->root()` internally.
- **WebView widgets are NOT `Composite`** — they create borderless child windows at absolute coordinates. Cannot be placed in `Box`/`Form`/`Tab`. Use `autoResize()`.
- **`fn () => echo …`** is a syntax error in PHP — use `print` or a `function () {}` body.
- **Do not add linter/formatter config** — upstream uses Mago. Prefer consistency.
- **Do not assume `phpunit.xml` is absent** — it exists. Pest reads it.
- **Composite GC trap** — temporary `Composite` objects (e.g. `(new SeparatorLine())->root()`) get `__destruct()` called at statement end via PHP's GC, which calls `uiControlDestroy()` on the underlying C widget while libui still holds a reference. **Always store Composites in named persistent variables.** If you see `uiControlVerifySetParent` errors, this is the cause.
- **`patches/` is append-only** — removing a file from `patches/` does NOT remove it from `vendor/`. Clean `vendor/` manually.

## Examples

```bash
php examples/all-components.php   # 6-tab demo: fields, custom widgets, dialogs, pickers, table, webview
php examples/menu.php              # Declarative vs imperative menu APIs
php examples/webview.php           # WebView with sidebar, JS ↔ PHP bridge
```
