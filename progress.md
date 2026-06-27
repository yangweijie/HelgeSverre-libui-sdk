# Progress Log: Windows WebView2 / TreeView 修复

## Session: 2026-06-27

### Phase 1: ✅ Ffi::init() 缺失
- **Status:** complete
- **Started:** 2026-06-27 ~10:00
- Actions:
  - `test-treeview.php` 创建 Window 和 TreeView 前未调用 `Ffi::init()`
  - 添加 `Ffi::init()` 到脚本顶部
- Files: `examples/test-treeview.php`

### Phase 2: ✅ Bridge DLL 加载失败
- **Status:** complete
- Actions:
  - `webview_bridge.dll` 无法加载：缺少 PebView 符号依赖
  - MinGW 重编译链接 PebView.dll
  - 修正 PebView 路径：`lib/windows/x64/PebView.dll` → `lib/windows/PebView.dll`
  - WebView.php 加载顺序：`loadPebView()` 先于 `loadBridge()`
- Files: `src/WebView.php:127-129, 480`

### Phase 3: ✅ WebView2 widget 0×0 尺寸
- **Status:** complete
- Actions:
  - 发现 PebView 内部创建 `webview_widget` 初始 0×0，依赖 WM_SIZE resize
  - bridge STATIC 窗口默认 WndProc 不转发 WM_SIZE
  - `wvb_create` 中 webview_create 后：FindWindowExW 找 widget → MoveWindow → SendMessage(WM_SIZE)
  - `wvb_move` 中同步更新 widget 尺寸
  - bridge 重新编译
- Files: `bridge/webview_bridge_win.c`

### Phase 4: ✅ TreeView JS→PHP 桥接断裂
- **Status:** complete
- Actions:
  - `INIT_SCRIPT_POST` 硬编码 macOS webkit.messageHandlers
  - 改为平台自适应：检测 webkit.messageHandlers → chrome.webview
- Files: `src/Widgets/TreeView.php:48-55`

### Phase 5: ✅ 示例重写
- **Status:** complete
- Actions:
  - 重写 `test-treeview.php`：先 show → 创建 TreeView → Loop::run()
  - 与 `webview.php` 保持相同模式
- Files: `examples/test-treeview.php`

## Test Results
| Test | Status |
|------|--------|
| Bridge DLL 加载 | ✅ |
| PebView + Bridge 符号解析 | ✅ |
| WebView2 嵌入渲染 | ✅ |
| TreeView HTML 渲染 | ✅ |
| 树节点点击 → PHP 回调 | ✅ |
| 展开/折叠事件 → PHP 回调 | ✅ |
| setData() 更新树数据 | ✅ |
| getSelectedPath() 读取路径 | ✅ |
| autoResize 窗口缩放 | ✅ |

## Files Modified
| File | Change |
|------|--------|
| `examples/test-treeview.php` | 重写：Ffi::init + 先 show + Loop::run |
| `src/WebView.php` | PebView 路径修正 + 加载顺序调换 |
| `src/Widgets/TreeView.php` | INIT_SCRIPT_POST 平台自适应 |
| `bridge/webview_bridge_win.c` | widget resize + wvb_move widget resize |
| `bridge/webview_bridge.dll` | 重新编译 |
