# Findings: Windows WebView2 嵌入机制

## PebView 的 Windows WebView2 架构

### 窗口层级
```
libui Window (parent_hwnd)
  └─ Bridge STATIC child (child_hwnd) — bridge 创建，定位在 (x,y,w,h)
       └─ PebView webview_widget — PebView 内部创建，初始尺寸 0×0
            └─ WebView2 controller — 浏览器引擎
```

### 关键发现：webview_widget 初始 0×0
- `win32_edge_engine` 构造函数在 `m_window`（即 bridge 的 STATIC 窗口）内创建 `webview_widget`
- `CreateWindowExW(WS_CHILD, "webview_widget", 0,0,0,0, m_window, ...)` — 初始尺寸为零
- `resize_widget()` 通过 `WM_SIZE` 在 m_window 上触发，读取父窗口 client rect 后 resize widget
- 但 bridge 的 STATIC 窗口使用默认 WndProc，不转发 `WM_SIZE` → PebView 永远看不到 resize

### WebView2 的 postMessage 桥接
- macOS (WKWebView): `window.webkit.messageHandlers.__webview__.postMessage(msg)`
- Windows (WebView2): `window.chrome.webview.postMessage(msg)`
- PebView 在 `embed()` 成功后自动注入 init script 设置 `window.chrome.webview`
- TreeView 的 `INIT_SCRIPT_POST` 之前只支持 macOS 路径

### WebView2 Runtime
- 已安装 v148.0.3967.54
- 路径: `C:\Program Files (x86)\Microsoft\EdgeWebView\Application\148.0.3967.54`
- PebView 内嵌 WebView2 loader（`mswebview2::loader`），不需要额外 `WebView2Loader.dll`

### bridge 编译要求 (Windows)
```
gcc -shared webview_bridge_win.c PebView.dll -o webview_bridge.dll -luser32
```
- 必须链接 PebView.dll（bridge 调用 `webview_create`/`webview_get_window`/`webview_destroy`）
- 原 README 中的编译命令缺少 PebView 链接

### PebView.dll 导出符号
```
webview_bind, webview_create, webview_destroy, webview_eval,
webview_get_window, webview_init, webview_navigate,
webview_return, webview_set_html
```
