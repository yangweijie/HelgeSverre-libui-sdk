# Task Plan: Windows 兼容性修复

## Goal
修复 HelgeSverre-libui-sdk 在 Windows 上的所有兼容性问题：WebView2 不渲染、JS↔PHP 桥接断裂、自绘控件不显示、TableModel 报错。

## Current Phase
全部完成 ✅

## Phases

### Phase 1: Ffi::init() 缺失 ✅
- `test-treeview.php` 在创建任何控件前未调用 `Ffi::init()`

### Phase 2: Bridge DLL 加载失败 ✅
- MinGW 重编译链接 PebView.dll
- 修正 PebView 路径、调整加载顺序

### Phase 3: WebView2 widget 0×0 尺寸 ✅
- `FindWindowExW` 找 widget + `MoveWindow` + `SendMessage(WM_SIZE)`

### Phase 4: TreeView JS→PHP 桥接断裂 ✅
- `INIT_SCRIPT_POST` 平台自适应：webkit.messageHandlers / chrome.webview

### Phase 5: 示例重写 ✅
- `test-treeview.php` 改为先 show 再创建 TreeView

### Phase 6: TableModel 报错 ✅
- `uiTableValueString()` 返回 const char* 被 FFI 自动转为 PHP string，移除 `borrowedString()` 包装
- 添加 patch: `patches/helgesverre/libui/src/TableModel.php`

### Phase 7: 自绘控件不显示 ✅
- **根因**：Windows 上 libui Area 在 padded Box 中 draw 回调不触发
- **发现**：`Build::stretchy($area)` 能让 draw 回调触发
- ToggleSwitch ✅ — 改用 `Build::stretchy()` + draw 用实际面积尺寸居中
- StatusIndicator ✅ — 同上
- CircleProgressBar ✅ — `Build::stretchy()` + timer(0) setSize + queueRedrawAll

### Phase 7b: CircleProgressBar 卡死修复 ✅
- **根因**：在 stretchy Area 上调用 `setSize()` 与 stretchy 容器冲突，导致卡死
- **发现**：Area 构造函数已有内置 timer(0) 处理初始绘制，无需额外 timer
- **修复**：移除 CircleProgressBar 和 StatusIndicator 中的冗余 timer 和 setSize()
- Files: `src/Widgets/CircleProgressBar.php`, `src/Widgets/StatusIndicator.php`

### Phase 7d: CircleProgressBar 中心文字 + 颜色自定义 ✅
- 进度百分比显示在圆环中心，字体自动缩放
- 支持 `setColor()` 自定义颜色
- 参考 monitor.php `label()` 模式绘制文字
- Files: `src/Widgets/CircleProgressBar.php`

### Phase 8: 示例 Tab 顺序恢复 ✅
- Custom 标签页临时放第一个方便调试，调试完后恢复原始顺序
- 恢复为：Fields → Custom → Dialogs → Pickers → Table → WebView

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| PebView 先于 bridge 加载 | bridge DLL 依赖 PebView 符号 |
| Widget 手动 resize | PebView widget 初始 0×0 |
| INIT_SCRIPT_POST 平台自适应 | macOS/Windows 不同桥接 |
| Area 必须 stretchy 才能 draw | libui Windows 后端限制 |
| 自绘 draw 用 `$params->areaWidth/Height` | stretchy 后 Area 尺寸变化，不能硬编码 |

## Errors Encountered
| Error | Resolution |
|-------|------------|
| Bridge DLL 加载失败 | MinGW 重编译 + 加载顺序修正 |
| WebView2 不渲染 | widget 手动 resize |
| TreeView 通信断裂 | INIT_SCRIPT_POST 平台自适应 |
| TableModel borrowedString 报错 | 移除 borrowedString 包装 |
| Area draw 回调不触发 | 改用 Build::stretchy |
| CircleProgressBar 拉伸变形 | draw 用实际面积尺寸居中 |
| CircleProgressBar 卡死 | 移除冗余 timer(0) + setSize() |
| CircleProgressBar 不可见 | minimum ring envelope + stretchy 布局 |
| CircleProgressBar 文字偏移 | monitor.php drawString 模式 |
