# Installation

## Requirements

- **PHP ≥ 8.5** (`ext-ffi` required)
- Platform library: `libui-ng` (ships prebuilt for macOS, Linux, Windows in the upstream)
- For WebView-based widgets: the PebView native library is downloaded and compiled via `composer install`

::: warning
The upstream requires PHP 8.5+. PHP 8.4.x will fail on `composer install`.
:::

## Install via Composer

```bash
composer require yangweijie/ui2
```

The `post-autoload-dump` script automatically:

1. Applies patches to the upstream vendor files (see [Patch System](/en/guide/patches))
2. Builds the native PebView library from source on macOS (requires Xcode CLI tools)

## Manual Patch Application

If you need to re-apply patches manually (e.g., after editing files in `patches/`):

```bash
php patch.php
```

## Building Native Components

```bash
# Build PebView native library
composer build:pebview

# Build WebView bridge (after PebView is ready)
composer build:bridge
```

## Standalone Binary Build

You can package your PHP app into a single portable `.exe` (Windows) or executable (macOS/Linux) using **phpmicro** — a PHP runtime that embeds PHP into a standalone binary.

### How It Works

1. **`build-phar.php`** — Bundles your entry script, Composer runtime dependencies, and platform-specific native DLLs (libui, PebView) into a `.phar` archive. The PHAR stub extracts native libraries to a temp directory at startup and sets the `LIBUI_LIB` environment variable for FFI.
2. **phpmicro (`micro.sfx`)** — A self-extracting PHP runtime. Concatenating it with your `.phar` produces a standalone executable.

### Requirements

- **PHP 8.5 CLI** (for running `build-phar.php`)
- **phpmicro** — Download `php-micro.tar.gz` from the [phpmicro releases](https://github.com/yangweijie/php-micro/releases) and extract `micro.sfx`
- **Windows only**: [Microsoft Visual C++ Redistributable](https://aka.ms/vs/17/release/vc_redist.x64.exe) (for `libui.dll` and `php_micro.dll`)

### Build Steps

```bash
# 1. Build the PHAR archive
php scripts/build-phar.php examples/all-components.php --output=app.phar --name=MyApp

# 2. Concatenate with micro.sfx to create a standalone executable
copy /b micro.sfx + app.phar MyApp.exe

# 3. Run it
.\MyApp.exe
```

The PHAR stub automatically:

- Extracts native `.dll` / `.so` / `.dylib` files to `sys_get_temp_dir()/ui2_<hash>/`
- Sets the `LIBUI_LIB` environment variable so `Ffi::libPath()` finds the correct library
- Cleans up extraction directories older than 7 days

### Important Notes

- **``uiInitOptions.Size``** — The framework's `Ffi::init()` properly sets the `Size` field of `uiInitOptions` before calling `uiInit()`. This is critical for phpmicro compatibility: without it, `uiInit()` silently fails on Windows and the event loop runs but no window appears.
- **Event loop** — `uiMain()` on Windows uses `GetMessage()`, which blocks even when no windows exist. If your window doesn't appear, check that `Ffi::init()` completed successfully (the `uiInit()` call returns an error string on failure).
- **Temp directory permissions** — The PHAR stub needs write access to `sys_get_temp_dir()`. Ensure the runtime user has appropriate permissions.
