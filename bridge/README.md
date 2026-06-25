# WebView Bridge

Platform-specific shared libraries that create a borderless child window
inside a libui window and embed a browser engine (WKWebView / WebKitGTK /
WebView2) within that child window.

## Compile

### macOS (ARM64 / x86_64)

```bash
clang -shared -fobjc-arc \
    bridge/webview_bridge.m \
    vendor/kingbes/pebview/lib/macos/arm64/PebView.dylib \
    -framework Cocoa \
    -o bridge/webview_bridge.dylib
```

### Linux (x86_64)

```bash
# Requires: libgtk-3-dev
#   Ubuntu/Debian: sudo apt install libgtk-3-dev
#   Fedora:        sudo dnf install gtk3-devel

gcc -shared -fPIC \
    bridge/webview_bridge_linux.c \
    $(pkg-config --cflags --libs gtk+-3.0) \
    -o bridge/webview_bridge.so
```

### Windows (x64)

```cmd
:: MSVC
cl /LD bridge/webview_bridge_win.c user32.lib /Fe:bridge/webview_bridge.dll

:: MinGW
gcc -shared bridge/webview_bridge_win.c -o bridge/webview_bridge.dll -luser32
```

## API

All three bridges export the same C functions:

```c
void* wvb_create(int debug, uintptr_t parent_handle, int x, int y, int w, int h);
void  wvb_move(void* wv, uintptr_t parent_handle, int x, int y, int w, int h);
void  wvb_destroy(void* wv);
```

- `wvb_create`: Create child window + webview engine at (x,y) with (w,h) size.
- `wvb_move`: Reposition/resize the child window (call on parent resize).
- `wvb_destroy`: Destroy the webview engine and close the child window.
