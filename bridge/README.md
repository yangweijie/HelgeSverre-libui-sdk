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
    -Wl,-rpath,$(pwd)/vendor/kingbes/pebview/lib/macos/arm64 \
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

# IME Bridge

Native text overlay used by `Surface`'s IME-capable fields (TextField /
SearchField / TextArea). A transparent, OS-native editable widget is floated
over the rendered field so the platform's IME / CJK composition works. All
three implementations export the **same C symbols**, so `Surface.php` uses one
FFI cdef regardless of platform.

| Platform | Source | Product |
|----------|--------|---------|
| macOS    | `ime_bridge.m`     | `ime_bridge.dylib` |
| Linux    | `ime_bridge_linux.c` | `ime_bridge.so` |
| Windows  | `ime_bridge_win.c` | `ime_bridge.dll` |

## Compile

### macOS (ARM64 / x86_64)

```bash
clang -dynamiclib -fobjc-arc \
    -framework Foundation -framework AppKit -framework QuartzCore \
    bridge/ime_bridge.m \
    -o bridge/ime_bridge.dylib
```

### Linux (x86_64)

```bash
# Requires: libgtk-3-dev
#   Ubuntu/Debian: sudo apt install libgtk-3-dev
#   Fedora:        sudo dnf install gtk3-devel

gcc -shared -fPIC \
    bridge/ime_bridge_linux.c \
    $(pkg-config --cflags --libs gtk+-3.0) \
    -o bridge/ime_bridge.so
```

### Windows (x64)

```cmd
:: MSVC
cl /LD bridge/ime_bridge_win.c /Fe:bridge/ime_bridge.dll user32.lib gdi32.lib

:: MinGW
gcc -shared bridge/ime_bridge_win.c -o bridge/ime_bridge.dll -luser32 -lgdi32
```

## API

All three bridges export the same C functions (called from PHP via FFI):

```c
void ime_create_textview(void* parent, double x, double y, double w, double h,
                         int vcenter, double font_size, const char* initial_text);
void ime_destroy_textview(void);
void ime_set_notify_callback(void (*callback)(const char*, int));
void ime_clear_notify_callback(void);
void ime_set_tab_callback(void (*callback)(int));
void ime_clear_tab_callback(void);
void* ime_get_textview(void);
int   ime_has_textview(void);
void  ime_set_text(const char* text);
int   ime_get_caret_position(void);
void  ime_set_caret_position(int pos);
int   ime_is_composing(void);
int   ime_make_textview_first_responder(void);
void  ime_clear_textview_first_responder(void);
void  ime_set_view_frame(void* view, double x, double y, double w, double h);
```

- macOS: overlay is a transparent `NSTextView` added as a subview of the Area.
- Windows: overlay is a Unicode Win32 `EDIT` control (IME-composition native).
- Linux: overlay is a borderless `GtkWindow` holding a `GtkEntry` / `GtkTextView`,
  positioned over the field's screen rectangle (GTK IM context is native).

