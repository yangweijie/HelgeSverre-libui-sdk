# Introduction

**yangweijie/ui2** is a thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui) — a native desktop GUI toolkit for PHP powered by `libui-ng` via FFI.

This package adds composite widgets, field helpers, picker dialogs, custom-drawn widgets, an embedded WebView engine, tree/file browser, code editor, and circular progress bars on top of the upstream's typed widget classes, custom 2D drawing, tables, menus, and dialogs.

## Project Structure

| Path | Purpose |
|------|---------|
| `src/` | Your code — `Yangweijie\Ui2\` namespace |
| `src/Composite.php` | Abstract base for multi-control widgets |
| `src/EmitsEvents.php` | Trait: `on(event, handler)` / `emit(event, data)` |
| `src/Fields/` | Label + input combos (TextField, NumberField, CheckboxField, etc.) |
| `src/Pickers/` | Modal picker dialogs (Color, Font, DatePicker, TimePicker) |
| `src/Dialogs/` | MessageBox, DialogConfirm, DialogPrompt |
| `src/Widgets/` | Custom-drawn: ToggleSwitch, StatusIndicator, CircleProgressBar, Toast, TableView, TreeView, CodeEditor |
| `src/Layout/` | TabContainer, GroupSection — convenience wrappers |
| `src/WebView.php` | Embedded browser via borderless child window |
| `assets/` | HTML/JS assets for WebView-based widgets |
| `patches/` | Override files for upstream (mirrored into vendor/ on install) |
| `bridge/` | C/ObjC source for WebView child-window bridge |
| `bootstrap.php` | Auto-loaded via composer autoload — registers Collision error handler |
