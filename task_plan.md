# Task Plan: Windows WebView2 / TreeView 修复

## Goal
修复 `test-treeview.php` 在 Windows 上无法运行的问题：WebView2 不渲染、JS↔PHP 桥接断裂。

## Current Phase
全部完成

## Phases

### Phase 1: Ffi::init() 缺失
- [x] `test-treeview.php` 在创建任何控件前未调用 `Ffi::init()`，导致 libui 未初始化
- **Status:** complete

### Phase 2: Bridge DLL 加载失败
- [x] `webview_bridge.dll` 编译时未链接 PebView.dll，导致 `webview_create` 等符号无法解析
- [x] 用 MinGW 重新编译：`gcc -shared webview_bridge_win.c PebView.dll -o webview_bridge.dll -luser32`
- [x] PebView.dll 路径错误：代码引用 `x64/PebView.dll`，实际在 `lib/windows/PebView.dll`
- [x] 加载顺序：PebView 必须先于 bridge 加载（bridge 依赖 PebView 符号）
- **Status:** complete

### Phase 3: WebView2 widget 0×0 尺寸
- [x] PebView 创建 `webview_widget` 子窗口初始尺寸为 0×0，依赖 `WM_SIZE` 触发 resize
- [x] bridge 中 STATIC 窗口的默认 WndProc 不转发 `WM_SIZE` 到 PebView
- [x] 修复：`webview_create` 后用 `FindWindowExW` 找到 widget，手动 `MoveWindow` + `SendMessage(WM_SIZE)`
- [x] `wvb_move` 同理添加 widget resize
- **Status:** complete

### Phase 4: TreeView JS→PHP 桥接断裂
- [x] `INIT_SCRIPT_POST` 硬编码 `window.webkit.messageHandlers`（macOS 专用）
- [x] Windows/WebView2 使用 `window.chrome.webview.postMessage`
- [x] 改为平台自适应检测
- **Status:** complete

### Phase 5: 示例重写
- [x] `test-treeview.php` 改为先 show 再创建 TreeView（与 `webview.php` 同模式）
- [x] 使用 `Loop::run()` 替代 `App::run()`
- **Status:** complete

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| PebView 先于 bridge 加载 | bridge DLL 依赖 PebView 的 `webview_create` 等符号 |
| Widget 手动 resize | PebView 的 `webview_widget` 初始 0×0，STATIC WndProc 不转发 WM_SIZE |
| `INIT_SCRIPT_POST` 平台自适应 | macOS 用 webkit.messageHandlers，Windows 用 chrome.webview |
| 先 show 后创建 WebView | 确保 WebView 子窗口 z-order 正确 |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| `Failed loading webview_bridge.dll (找不到指定的模块)` | 1 | MinGW 重编译链接 PebView.dll |
| `Failed loading webview_bridge.dll (找不到指定的模块)` | 2 | loadPebView 先于 loadBridge + 修正路径 |
| 树视图不显示 | 1 | 修复 widget 0×0 尺寸问题 |
| JS→PHP 通信断裂 | 1 | INIT_SCRIPT_POST 平台自适应 |
| 示例无响应 | 1 | 添加 Ffi::init() + 调整创建顺序 |
