# Patch System

Instead of forking the upstream library, this project overrides specific files via a patch layer:

1. Files in `patches/` mirror the path structure under `vendor/`
2. On `composer install` / `composer update`, the `post-autoload-dump` script (`patch.php`) recursively copies everything from `patches/` into `vendor/`
3. This lets you extend widgets, add methods, or fix behavior without maintaining a separate fork

## Currently Patched Files

Under `patches/helgesverre/libui/src/`:

| File | Additions |
|---|---|
| `Ffi.php` | `uninit()` order fix: retained closures cleared before `uiUninit()` + triple GC; PHAR `libPath()`/`readHeader()` support; debug mode |
| `App.php` | `gc_collect_cycles()` before/after destroy loop; explicit Window destroy in `finally` block |
| `Control.php` | `__destruct()` for toplevel widgets; `clearRetainedCallbacks()` |
| `Box.php` | Accepts `Composite` children; `horizontal()` static factory; `appendStretchy()` |
| `Form.php` | Accepts `Composite` children; `values()`/`setValues()` for HasValue fields; `appendStretchy()` |
| `Grid.php` | Accepts `Composite` children; `appendAt()` positional args; `place()` shortcut |
| `Group.php` | Accepts `Composite` children; `titled()` static factory |
| `Tab.php` | Accepts `Composite` children in `append()`/`appendMargined()` |
| `Menu.php` | Fluent builder API; improved `MenuOrderException` |
| `MenuItem.php` | `onClick()` replaces handler; `removeOnClick()`; error handlers |
| `Window.php` | `centered()` / `centeredOn()` positioning; `run()` single-window loop; `setWindowIcon()`; `markExternallyClosed()` / `isExternallyClosed()` |
| `Exception/MenuOrderException.php` | Carries the Window title that locked menus |
| `Draw/DrawContext.php` | Fluent builder: `fillRect`, `strokeCircle`, `withSave()`, `drawString()` with explicit `free()` |
| `Draw/Path.php` | `wedge()`, `polygon()`, `ellipse()`, `roundedRect()`, `quadTo()`, `bezierThrough()` |
| `Draw/Params/AreaKeyEvent.php` | Semantic query methods |
| `Draw/Params/AreaMouseEvent.php` | Semantic query methods |

## Important: Append-Only

`patches/` is **append-only** — stale patches are never removed. If you delete a file from `patches/`, the previous copy still lives in `vendor/`. Clean it manually.

::: danger
Do not edit files inside `vendor/` directly. Place overrides in `patches/` — they will be mirrored on next install.
:::
