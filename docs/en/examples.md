# Examples

Run the examples from the project root:

```bash
php examples/all-components.php   # Full demo with 6 tabs showing all widgets
php examples/menu.php              # Declarative vs imperative menu APIs
php examples/webview.php           # WebView with sidebar, JS ↔ PHP bridge
php examples/tetris.php             # Full Tetris game using Area custom drawing
```

## all-components.php

Demonstrates every widget in this package across 6 tabs:

1. **Fields** — all input field types
2. **Custom** — ToggleSwitch, StatusIndicator, CircleProgressBar
3. **Dialogs** — MessageBox, DialogConfirm, DialogPrompt, Toast
4. **Pickers** — Color, Font, Date, Time pickers
5. **Table** — Tabular data with TableView
6. **WebView** — TreeView and CodeEditor

## tetris.php

A complete Tetris game implemented entirely with `Area` custom drawing — no external game engine or canvas needed. Demonstrates:

- **`Area` + `AreaDelegate`** — custom 2D rendering with `draw()`, keyboard handling with `key()`
- **`Loop::repeat()`** — gravity timer ticking the game board at increasing speeds
- **`DrawContext` builder** — cell rendering with 3D bevel effect, ghost piece preview, grid lines
- **Keyboard input** — arrow keys (via `ExtKey`) for movement, rotation, soft/hard drop
- **Game mechanics** — 7 tetrominoes, wall kicks, line clearing, score/level/lines tracking
- **Overlays** — pause screen, game over overlay drawn directly on the Area

```bash
php examples/tetris.php
```

Controls: ← → ↓ move, ↑ rotate, Space hard drop, R restart, Escape pause/resume.

## Test Files

Additional test scripts in `examples/` for individual features:

| Script | Feature |
|---|---|
| `test-fields.php` | Field component tests |
| `test-widgets.php` | Custom widget tests |
| `test-pickers.php` | Picker dialog tests |
| `test-circle-progress.php` | Circle progress bar |
| `test-treeview.php` | TreeView widget |
| `test-codeeditor.php` | CodeEditor widget |
| `test-tray.php` | System tray |
| `test-context-menu.php` | Context menu (area and standard) |
| `test-global-hotkey.php` | Global hotkey registration |
| `toast-test.php` | Toast notification |
| `test-system-info.php` | System information |
| `test-log.php` | Log viewer |
| `test-process-util.php` | Process utility |
| `test-svg.php` | SVG rendering |
| `test-debug-bridge.php` | Bridge debugging |
| `test-set-icon.php` | App icon setting |
| `tetris.php` | Full Tetris game — Area custom drawing, keyboard input, gravity timer, ghost piece, score system |

## Packaging as Standalone Binary

Package your ui2 app into a standalone executable (no PHP required on the target machine):

### Prerequisites

**macOS / Linux:**
```bash
# 1. Install static-php-cli and build micro.sfx
composer install:spc

# 2. Verify the micro.sfx was built
ls ~/.spc/micro.sfx
```

**Windows:**
```batch
:: Install static-php-cli and build micro.sfx
scripts\install-spc.bat

:: Verify
dir %USERPROFILE%\.spc\micro.sfx
```

> Downloads `static-php-cli` and builds a static PHP interpreter (`micro.sfx`) with FFI, PHAR, mbstring, tokenizer, and filter extensions. This is a one-time setup that takes 10-30 minutes (compiles PHP from source).
>
> **Windows note**: Requires Visual Studio 2022 with "Desktop development with C++" workload for PHP source compilation. Windows 10 Build 17063+ (for `curl.exe`) required.

### Build

```bash
# Build a PHAR archive (any project)
composer build:phar -- examples/tetris.php --output=tetris.phar

# Build a standalone binary (requires micro.sfx)
composer build:binary -- examples/tetris.php --name=Tetris --icon=icon.png

# Run the binary
./dist/Tetris
```

The build pipeline:
1. **PHAR** — bundles your app code, vendor dependencies, and native `libui` shared libraries
2. **Binary** — concatenates `micro.sfx` + PHAR into a single executable
3. **Icon** — macOS: generates `.app` bundle with `AppIcon.icns`; Linux: `.desktop` + PNG; Windows: `.ico` via `rcedit`

### From a dependent project

```bash
# In your project that requires yangweijie/ui2:
php vendor/yangweijie/ui2/scripts/build-phar.php my-app.php --output=my-app.phar
php vendor/yangweijie/ui2/scripts/build-binary.php --phar=my-app.phar --name=MyApp
```

> **How it works**: The PHAR stub extracts `libui-ng` shared libraries to a temp directory at startup (FFI's `dlopen()` requires real filesystem paths). Old extractions are cleaned up after 7 days.

### Native Library Extraction

At runtime, the packaged binary:
1. Extracts `libui` shared libraries (`.dylib`/`.so`/`.dll`) to `sys_get_temp_dir()`
2. Sets the `LIBUI_LIB` environment variable so `Ffi::get()` finds them
3. Runs your app — FFI loads the native library from the real filesystem
4. Cleans up extractions older than 7 days

### Composer Commands

| Command | Description |
|---------|-------------|
| `composer build:phar -- <entry> [options]` | Build a PHAR from a PHP entry file |
| `composer build:binary -- <entry> [options]` | Build a standalone binary |
| `composer install:spc` | Install static-php-cli and build micro.sfx |

### Scripts Reference

| Script | Description |
|--------|-------------|
| `scripts/build-phar.php` | PHAR archive builder (bundles app + vendor + native libs) |
| `scripts/build-binary.php` | Binary orchestrator (PHAR → micro.sfx → icon → .app/.exe) |
| `scripts/install-spc.sh` | static-php-cli installer + micro.sfx builder (macOS/Linux) |
| `scripts/install-spc.bat` | static-php-cli installer + micro.sfx builder (Windows) |
