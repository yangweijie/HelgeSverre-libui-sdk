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
