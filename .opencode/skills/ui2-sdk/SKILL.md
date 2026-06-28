---
name: ui2-sdk
description: yangweijie/ui2 — Native desktop GUI for PHP 8.5+ via FFI. Thin convenience layer over helgesverre/libui with composite widgets, fields, pickers, dialogs, custom-drawn widgets, WebView, tree/file browser, code editor, circular progress bars, and SVG support.
author: yangweijie
platform: github
source: https://github.com/yangweijie/HelgeSverre-libui-sdk
tags: [php, ffi, gui, desktop, libui, native, webview, widgets]
version: 1.0.0
generated: 2026-06-28T19:45:00+08:00
---

# ui2-sdk — Native Desktop GUI for PHP

## Overview

**ui2** (`yangweijie/ui2`) is a thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui) — the native desktop GUI library for PHP via FFI. It adds composite widgets, fields, pickers, dialogs, custom-drawn widgets, WebView (embedded browser), tree/file browser, code editor, circular progress bars, SVG display, system tray, global hotkeys, and more.

- **PHP Version**: 8.5+ (requires `ext-ffi`)
- **Platforms**: macOS, Linux, Windows (native look and feel on each)
- **License**: MIT

## Quick Start

```bash
composer require yangweijie/ui2
```

```php
<?php
require 'vendor/autoload.php';

use Yangweijie\Ui2\Fields\TextField;
use Yangweijie\Ui2\Layout\TabContainer;
use Libui\App;
use Libui\Window;
use Libui\Ffi;

Ffi::init();

$window = new Window('Hello ui2', 600, 400, true);
$window->setChild(new TabContainer([
    'Tab 1' => new TextField('Name:'),
    'Tab 2' => new TextField('Email:'),
]));

App::new()->run($window);
```

## Skill Structure (Layered Documentation)

This skill is organized into multiple focused documents:

| Document | Purpose |
|----------|---------|
| `docs/architecture.md` | Project architecture, Composite pattern, patch system, data structures |
| `docs/widgets.md` | Complete widget catalog with usage examples |
| `docs/patches.md` | Patch system: extending upstream without forking |
| `docs/webview.md` | WebView, TreeView, CodeEditor — embedded browser integration |
| `docs/fields-pickers-dialogs.md` | Form fields, picker dialogs, message boxes |
| `docs/custom-widgets.md` | Custom-drawn widgets: CircleProgressBar, ToggleSwitch, StatusIndicator, SvgView |
| `docs/system.md` | System integration: Tray, GlobalHotkey, SystemInfo, ProcessUtil |
| `docs/development.md` | Development workflow, building, testing, debugging |
| `docs/troubleshooting.md` | Common issues and solutions |

## Key Features

### Widget Library
- **Fields** (18): TextField, NumberField, PasswordField, TextAreaField, SearchField, ComboBoxField, EditableComboBoxField, CheckboxField, RadioGroup, SliderField, ProgressBarField, DatePickerField, FilePickerField, SeparatorLine
- **Pickers** (4): ColorPickerDialog, FontPickerDialog, DatePickerDialog, TimePickerDialog
- **Dialogs** (3): MessageBox, DialogConfirm, DialogPrompt
- **Custom Widgets** (8): CircleProgressBar, ToggleSwitch, StatusIndicator, Toast, TableView, TreeView, CodeEditor, SvgView
- **Layout** (2): TabContainer, GroupSection

### WebView Integration
- Native browser engines: WKWebView (macOS), WebKitGTK (Linux), WebView2 (Windows)
- JS ↔ PHP bridge via `window.chrome.webview.postMessage()` / `window.webkit.messageHandlers`
- TreeView and CodeEditor built on WebView

### Patch System
- Extend upstream `helgesverre/libui` classes without forking
- Place overrides in `patches/` — mirrored into `vendor/` on `composer install`
- Currently patches: Box, Form, Grid, Group, Tab, Menu, MenuItem, Window, DrawContext, Path, Area events

### System Integration
- **Tray**: Cross-platform system tray with menus
- **GlobalHotkey**: Register global keyboard shortcuts
- **SystemInfo**: CPU, memory, architecture detection
- **ProcessUtil**: Process management utilities

## Installation

```bash
composer require yangweijie/ui2
```

### Native Dependencies

The library uses `libui-ng` (bundled in upstream) and PebView for WebView:

```bash
# Build PebView (required for WebView/TreeView/CodeEditor)
composer build:pebview

# Build WebView bridge
composer build:bridge
```

See `docs/development.md` for platform-specific build instructions.

## Usage Examples

See `docs/examples.md` for runnable examples:
- `examples/all-components.php` — 6-tab demo showcasing all widgets
- `examples/webview.php` — WebView with sidebar and JS bridge
- `examples/menu.php` — Declarative vs imperative menu APIs
- `examples/test-fields.php` — Fields showcase

## Documentation Index

- [Architecture & Patterns](docs/architecture.md)
- [Widget Catalog](docs/widgets.md)
- [Patch System](docs/patches.md)
- [WebView Integration](docs/webview.md)
- [Fields, Pickers & Dialogs](docs/fields-pickers-dialogs.md)
- [Custom Widgets](docs/custom-widgets.md)
- [System Integration](docs/system.md)
- [Development Guide](docs/development.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Examples](docs/examples.md)

## Resources

- **GitHub**: https://github.com/yangweijie/HelgeSverre-libui-sdk
- **Upstream**: https://github.com/HelgeSverre/libui
- **PebView**: https://github.com/kingbes/pebview
- **Packagist**: https://packagist.org/packages/yangweijie/ui2