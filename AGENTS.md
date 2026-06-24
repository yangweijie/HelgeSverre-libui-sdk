# AGENTS.md — yangweijie/ui2

This is a **thin wrapper** around [`helgesverre/libui`](https://github.com/HelgeSverre/libui), a
native desktop GUI library for PHP 8.5+ using `libui-ng` via FFI. The upstream provides typed
widget classes, custom 2D drawing, tables, menus, and dialogs — all driven through
`Libui\Ffi::init()` → `\FFI::cdef()` → prebuilt `libui.<dll|so|dylib>`.

## Project structure

| Path | Purpose |
|---|---|
| `src/` | **Your code** — namespace `Yangweijie\Ui2\` (`Pickers\*`, more as built) |
| `patches/` | Overlay files **copied into `vendor/`** on `composer install` (see `patch.php`) |
| `patch.php` | `post-autoload-dump` script: mirrors `patches/` tree into `vendor/` (overriding upstream files when needed) |
| `vendor/helgesverre/libui/` | Upstream library — namespace `Libui\`, not yours. Do not edit directly; use `patches/` instead. |

## How the patch system works

`composer install`/`composer update` triggers `@php patch.php`, which recursively copies
everything from `patches/` into `vendor/`. This lets you override or extend upstream files
without forking the dependency. The `patches/` directory mirrors the path structure under
`vendor/` — e.g. `patches/helgesverre/libui/src/Ffi.php` would shadow the real one.

## Upstream essentials

The upstream is thoroughly documented; these are the points an agent is most likely to miss:

- **Always call `Libui\Ffi::init()`** before any widget constructor. It is idempotent.
- **`Window::run()`** = show window + run event loop + cleanup. For multi-window apps use `Libui\App::run()`.
- **Menus must be created before the first window** — `Menu` enforces this at runtime (`MenuOrderException`).
- **Event callbacks** must return `void`; exceptions are caught and printed to `STDERR`, they do not crash
  the process but are silently swallowed. Always use try/catch in callbacks if the handler can fail.
- **Closures passed to libui C callbacks are retained** by `Ffi::$retained` and `Control::$retainedCallbacks`
  to prevent GC of native trampolines. You do not need to retain closures yourself.
- **`fn () => echo …` is a syntax error** in PHP — use `print` or a `function () { … }` body.
- **`Ffi::get()`** lazily loads the C header and native lib. Returns the singleton `\FFI` handle;
  all 299 raw `ui*()` functions are callable on it.
- **Generated code**: `src/Native/libui.gen.h` (FFI header) and `src/Generated/*` (typed classes)
  are generated from `ui.h` via `composer regen`. Never edit them by hand.
- **Prebuilt binaries** ship in `lib/<platform>/libui.*`; override with `$LIBUI_LIB` env var.

## Available from upstream

The upstream `composer.json` exposes these commands (run from `vendor/helgesverre/libui/`):

```
composer test         Full PHPUnit suite
composer stan         PHPStan level 6
composer format       Mago formatter
composer regen        Regenerate FFI header + typed classes from ui.h
```

This project (`yangweijie/ui2`) currently has **no CI, no linting** configured.

## Testing

Tests are in `tests/` and run via `vendor/bin/phpunit` or `composer test` (once configured). When adding tests, note:
- The upstream uses PHPUnit 13 with `@group gate` (FFI header acceptance) and `@group smoke` (widget construction, no event loop).
- A test can call `Ffi::init()` multiple times — it is idempotent.
- `Testing\CallbackSpy` and `Testing\Inspect` are available from the upstream for assertion-based testing without an event loop.

## Git state

- Branch `main` — initial commit with scaffolding.
- Remote: `git@github.com:yangweijie/HelgeSverre-libui-sdk.git`
- `.gitignore` ignores `/vendor/`, `/.phpunit.cache/`, `/.serena/`.

## Common agent pitfalls

- Do not create PHP files that use `Libui\` classes without `require 'vendor/autoload.php'` first.
- Do not edit files inside `vendor/` directly — place overrides in `patches/` (mirrored on next install).
- Do not assume `phpunit.xml` exists — one must be created if tests are added.
- Do not add linter/formatter config without checking the upstream's choice (Mago). Prefer consistency.
- PHP 8.5+ only — `readonly` classes, property hooks, and other 8.x features are available.
