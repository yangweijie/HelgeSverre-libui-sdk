# AGENTS.md — yangweijie/ui2

This is a **thin wrapper** around [`helgesverre/libui`](https://github.com/HelgeSverre/libui), a
native desktop GUI library for PHP 8.5+ using `libui-ng` via FFI. The upstream provides typed
widget classes, custom 2D drawing, tables, menus, and dialogs — all driven through
`Libui\Ffi::init()` → `\FFI::cdef()` → prebuilt `libui.<dll|so|dylib>`.

## Project structure

| Path | Purpose |
|---|---|
| `src/` | **Your code** — namespace `Yangweijie\Ui2\` — Composite base, EmitsEvents trait, Fields, Pickers |
| `patches/` | Overlay files **copied into `vendor/`** on `composer install` (see `patch.php`) |
| `patch.php` | `post-autoload-dump` script: mirrors `patches/` tree into `vendor/` |
| `vendor/helgesverre/libui/` | Upstream library — namespace `Libui\`, not yours. Do not edit directly; use `patches/` instead. |
| `tests/` | Pest tests, run via `vendor/bin/pest` |
| `examples/` | Runnable examples (e.g. `php examples/menu.php`) |

## How the patch system works

`composer install`/`composer update` triggers `@php patch.php`, which recursively copies
everything from `patches/` into `vendor/`. This lets you override or extend upstream files
without forking. The `patches/` directory mirrors the path structure under `vendor/`.

**Currently patched files** (all under `patches/helgesverre/libui/src/`):

| File | What it adds |
|---|---|
| `Box.php` | Accepts `Composite` children; `horizontal()` static factory with padded option; `appendStretchy()` |
| `Form.php` | Accepts `Composite` children; `values()`/`setValues()` for HasValue fields; `appendStretchy()`; tracks field list in sync with `delete()` |
| `Grid.php` | Accepts `Composite` children; `appendAt()` (readable positional args); `place()` shortcut |
| `Group.php` | Accepts `Composite` children; `titled()` static factory |
| `Tab.php` | Accepts `Composite` children in `append()`/`appendMargined()` |
| `Menu.php` | Declarative fluent builder API (`create()`/`item()`/`separator()`/`quitItem()`); improved `MenuOrderException` with window title |
| `MenuItem.php` | `onClick()` replaces handler (does not stack C trampolines); per-call & global error handlers; `removeOnClick()` |
| `Window.php` | Tracks `menusLocked()`/`firstWindowTitle()` for MenuOrderException; `centered()` positioning; `getContentSize()`/`getPosition()`; `onClose()`; `run()` (single-window loop); `resetMenuLock()` for tests |
| `Exception/MenuOrderException.php` | Carries the title of the Window that locked menus |
| `Draw/DrawContext.php` | `fillPath()`/`strokePath()` builder pattern; `fillRect`/`strokeRect`/`fillCircle`/`strokeCircle`/`fillEllipse`/`strokeEllipse`/`fillRoundedRect`/`strokeRoundedRect`/`fillPolygon`/`strokePolygon`/`fillArc`/`strokeArc`/`strokeLine`/`line`/`dot`; `withSave()`; `drawString()` |
| `Draw/Path.php` | `wedge()` (pie slices); `polygon()`; `ellipse()`; `roundedRect()`; `quadTo()` (quadratic → cubic promotion); `bezierThrough()`; `line()`/`circle()`/`arc()` shorthands |
| `Draw/Params/AreaKeyEvent.php` | Semantic query methods |
| `Draw/Params/AreaMouseEvent.php` | Semantic query methods |

## Your code (src/)

### Core abstractions

- **`Composite`** (`src/Composite.php`) — abstract base for widgets built from multiple controls.
  Implements `HasValue` with no-op stubs. Subclasses override `root()` (returns the top-level
  `Control`) and optionally `value()`/`setValue()`.
- **`EmitsEvents`** (`src/EmitsEvents.php`) — trait adding `on(event, handler)` / `emit(event, data)`.
  Used by all Field composites.

### Fields (`src/Fields/`)

Each is a `Composite` that pairs a `Label` with a specific input widget in a horizontal `Box`:

| Class | Input widget | Value type | Notes |
|---|---|---|---|
| `TextField` | `Entry` | `string` | |
| `SearchField` | `Entry::search()` | `string` | `onChanged` may debounce on macOS |
| `PasswordField` | `Entry::password()` | `string` | Text masked on screen, readable via `value()` |
| `NumberField` | `Spinbox` | `int` | Requires min/max range |
| `SliderField` | `Slider` | `int` | Has live value readout label |
| `FilePickerField` | `Entry` (readonly) + `Button` | `string` | Requires parent `Window`; opens native file dialog |
| `CheckboxField` | `Checkbox` | `bool` | Checkbox with label |
| `RadioGroup` | `RadioButtons` | `int` | Selected index (0-based) |
| `ComboBoxField` | `Combobox` | `int` | Selected index (0-based); `addOptions()` |
| `EditableComboBoxField` | `EditableCombobox` | `string` | User-typable combo; `addOptions()` |
| `DatePickerField` | `DateTimePicker` | `\DateTimeImmutable` | `dateOnly()`/`timeOnly()` factories |
| `TextAreaField` | `MultilineEntry` | `string` | Vertical label + stretchy text area |
| `ProgressBarField` | `ProgressBar` | (none) | `setProgress()`, `indeterminate()` |
| `SeparatorLine` | `Separator` | (none) | Thin horizontal divider |

All value-holding fields emit `'change'` via the `EmitsEvents` trait.

### Pickers (`src/Pickers/`)

| Class | What it returns | Notes |
|---|---|---|
| `ColorPickerDialog` | `?Color` | Wraps `ColorButton` in a temp modal window; blocking `uiMainStep(1)` loop |
| `FontPickerDialog` | `?FontDescriptor` | Wraps `FontButton` in a temp modal window; blocking `uiMainStep(1)` loop |

Both use a **nested event-loop step** — they do NOT call `uiQuit()`, so they can be called from
within an already-running `uiMain()` loop without side effects.

### Dialogs (`src/Dialogs/`)

| Class | Description |
|---|---|
| `MessageBox` | Static helpers: `info()`, `warning()`, `error()` — wraps upstream `Dialogs::msgBox()`/`msgBoxError()` |

### Widgets (`src/Widgets/`)

Custom-drawn widgets using `Area` + `AreaDelegate`:

| Class | Description |
|---|---|
| `ToggleSwitch` | Area-based toggle switch; accepts composite |
| `StatusIndicator` | Colored dot indicator (online/offline); `setColor()` / `setColorHex()` |

## Upstream essentials

- **Always call `Libui\Ffi::init()`** before any widget constructor. It is idempotent.
- **`Window::run()`** = show window + run event loop + cleanup. For multi-window apps use `Libui\App::run()`.
- **Menus must be created before the first window** — `Menu` enforces this at runtime (`MenuOrderException`).
- **Event callbacks** must return `void`; exceptions are caught and printed to `STDERR`. Always use try/catch
  in callbacks if the handler can fail.
- **Closures passed to libui C callbacks are retained** by `Ffi::$retained` and `Control::$retainedCallbacks`.
  You do not need to retain closures yourself.
- **`fn () => echo …` is a syntax error** in PHP — use `print` or a `function () { … }` body.
- **`Ffi::get()`** lazily loads the C header and native lib. Returns the singleton `\FFI` handle.
- **Generated code**: `src/Native/libui.gen.h` and `src/Generated/*` are generated from `ui.h`
  via `composer regen` (in the upstream). Never edit by hand.
- **Prebuilt binaries** ship in `lib/<platform>/libui.*`; override with `$LIBUI_LIB` env var.

## Available from upstream

The upstream `composer.json` exposes these commands (run from `vendor/helgesverre/libui/`):

```
composer test         Full PHPUnit suite
composer stan         PHPStan level 6
composer format       Mago formatter
composer regen        Regenerate FFI header + typed classes from ui.h
```

This project (`yangweijie/ui2`) has **no CI, no linting** configured. To run tests:

```
vendor/bin/pest
```

Adding a `composer test` script would require adding `"test": "pest"` to `scripts` in
`composer.json`.

## Testing

Tests use Pest 4 (built on PHPUnit 12, bootstrapped via `vendor/autoload.php`). The `phpunit.xml`
already exists with a single `yangweijie/ui2` suite pointing at `tests/`. When adding tests, note:

- `tests/Pest.php` is the Pest configuration file (standard for all Pest projects).
- The upstream uses `@group gate` (FFI header acceptance) and `@group smoke` (widget construction, no event loop).
- `Testing\CallbackSpy` and `Testing\Inspect` are available from the upstream for assertion-based testing
  without an event loop.
- `Window::resetMenuLock()` is available for tests that need to construct a Menu after a Window.
- Existing test (`tests/DialogsTest.php`) tests a private upstream method via reflection — no FFI needed.
- The original test was a PHPUnit class; it has been migrated to Pest test functions. Write new tests
  in Pest style (use `test()`/`it()`, `expect()`).

## PHP version

- **Upstream requires PHP ≥ 8.5** (`ext-ffi` required).
- Installed PHP on this dev machine is `8.4.19` — running `composer install` will fail until upgraded.
- The project uses `readonly` properties, property hooks, and other 8.x features.

## Git state

- Branch `main`. Remote: `git@github.com:yangweijie/HelgeSverre-libui-sdk.git`
- `.gitignore` ignores `/vendor/`, `/.phpunit.cache/`, `/.serena/`, `/.omo/`.
- `vendor/` is NOT currently installed — run `composer install` after PHP ≥ 8.5 is available.

## Common agent pitfalls

- **Do not edit files inside `vendor/` directly** — place overrides in `patches/` (mirrored on next install).
- **Do not create PHP files using `Libui\` classes without `require 'vendor/autoload.php'` first.**
- **Two Menu APIs coexist in the patches**: declarative (`Menu::create('File')->item('Open', fn)...`)
  and imperative (`new Menu('File')` then `$menu->appendItem(...)`). Both are valid. The imperative
  style is needed when you need the `MenuItem` reference later.
- **Patched `MenuItem::onClick()` replaces the handler** — it does NOT stack. Calling `onClick()` a
  second time replaces the first callback. This differs from most libui callbacks where setting
  a new handler adds another C trampoline.
- **`Window::run()` calls `Ffi::uninit()` in a `finally` block** — any code after `run()` in the
  same script runs in a torn-down libui state. Use the `$afterClose` callback for cleanup instead.
- **Container patches (Box, Form, Grid, Group, Tab) accept `Composite`** — you pass a `Composite` where you
  would pass a `Control`. The patch calls `$composite->root()` internally.
- **Tests now use Pest** — run with `vendor/bin/pest`. Existing test migrated from PHPUnit.
- **Do not assume `phpunit.xml` is absent** — it exists. Do not recreate it. Pest reads it.
- **Do not add linter/formatter config** without checking the upstream's choice (Mago). Prefer consistency.
