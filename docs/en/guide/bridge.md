# Bridge System

The `bridge/` directory contains platform-specific C source files that connect PHP to native WebView APIs:

| Platform | Source | Binary |
|---|---|---|
| macOS | `bridge/webview_bridge.m` (Objective-C) | `webview_bridge.dylib` |
| Linux | `bridge/webview_bridge_linux.c` | `webview_bridge.so` |
| Windows | `bridge/webview_bridge_win.c` | `webview_bridge.dll` |

The bridge library is loaded via FFI and handles creating, moving, and destroying the child window that hosts the native WebView.

## Building the Bridge

```bash
# Build PebView native library first
composer build:pebview

# Then build the WebView bridge
composer build:bridge
```

## Dock Icon (macOS)

The bridge also provides `wvb_set_dock_icon()` which calls `[NSApp setApplicationIconImage:]` on macOS to set the dock/taskbar icon. This is used internally by `Window::setWindowIcon()`.
