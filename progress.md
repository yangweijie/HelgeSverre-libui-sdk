# Progress Log: Windows 兼容性修复

## Session: 2026-06-27

### Phase 1-5: ✅ WebView2 / TreeView 修复
- Ffi::init() 缺失、Bridge DLL 加载、widget 0×0、JS 桥接、示例重写
- Files: `src/WebView.php`, `bridge/webview_bridge_win.c`, `src/Widgets/TreeView.php`, `examples/test-treeview.php`

### Phase 6: ✅ TableModel 修复
- **根因**：`uiTableValueString()` 返回 `const char*`，PHP FFI 自动转为 PHP string，但 `borrowedString()` 需要 `FFI\CData` 参数
- **修复**：移除 `borrowedString()` 包装，直接使用 `uiTableValueString()` 返回值
- Files: `patches/helgesverre/libui/src/TableModel.php`

### Phase 7: ✅ 自绘控件不显示
- **根因**：padded Box 中非 stretchy Area draw 回调不触发
- ToggleSwitch ✅ — `Build::stretchy($area)` + draw 居中
- StatusIndicator ✅ — 同上
- CircleProgressBar ✅ — `Build::stretchy()` + timer(0) setSize + queueRedrawAll
- Tab 顺序已恢复：Fields → Custom → Dialogs → Pickers → Table → WebView

### Phase 7b: ✅ CircleProgressBar 卡死修复
- **根因**：在 stretchy Area 上调用 `setSize()` 与 stretchy 容器冲突，导致卡死
- **发现**：Area 构造函数已有内置 timer(0) 处理初始绘制，无需额外 timer
- **修复**：移除 CircleProgressBar 和 StatusIndicator 中的冗余 timer 和 setSize()
- Files: `src/Widgets/CircleProgressBar.php`, `src/Widgets/StatusIndicator.php`

### Phase 7c: ✅ CircleProgressBar 可见性修复
- **根因**：非滚动 Area 在 libui 中 preferred size = 0；hbox 中高度受 Label 限制（17px）；radius 计算为负值导致画不出
- **修复 1 — draw() minimum ring envelope**：`$diameter = max($minDiameter, min($w, $h) - 8)` 保证任何 Area 尺寸都能画出可见环
- **修复 2 — 布局拉伸**：外层 `Build::stretchy($groupCircle)` + 内层 `Build::vbox(Build::stretchy($area), $label)` 让 Area 获得完整垂直空间
- **验证**：all-components.php 和 test-circle-progress.php 均正常显示，无卡死
- Files: `src/Widgets/CircleProgressBar.php`, `examples/all-components.php`, `examples/test-circle-progress.php`

### 关键测试结果
| 测试 | 结果 |
|------|------|
| Area 直接作为 Window child | ✅ draw 触发 |
| Area 在 unpadded Box | ✅ draw 触发 |
| Area 在 padded Box (非 stretchy) | ❌ draw 不触发 |
| Area 在 padded Box + `Build::stretchy()` | ✅ draw 触发 |
| Area 在 padded Box + timer(0) setSize + queueRedrawAll | ❌ draw 不触发 |
| 非滚动 Area 在 stretchy Box | ✅ draw 触发 |
| 滚动 Area 在 stretchy Box | ⚠️ 有滚动条问题 |

### Files Modified (本次会话)
| File | Change |
|------|--------|
| `examples/test-treeview.php` | 重写：Ffi::init + 先 show + Loop::run |
| `src/WebView.php` | PebView 路径修正 + 加载顺序调换 |
| `src/Widgets/TreeView.php` | INIT_SCRIPT_POST 平台自适应 |
| `bridge/webview_bridge_win.c` | widget resize + wvb_move widget resize |
| `patches/helgesverre/libui/src/TableModel.php` | 移除 borrowedString 包装 |
| `patches/helgesverre/libui/src/Area.php` | timer(0) 一次性 redraw |
| `src/Widgets/CircleProgressBar.php` | 非滚动 Area + minimum ring envelope + 移除 timer(33) setSize |
| `src/Widgets/ToggleSwitch.php` | draw 用实际面积居中 |
| `src/Widgets/StatusIndicator.php` | draw 用实际面积居中 + 移除冗余 timer(0) setSize |
| `examples/all-components.php` | stretchy Group + 内层 vbox + Custom 标签顺序恢复 |
| `examples/test-circle-progress.php` | STEP 0-6 bisection test with Ffi::init |
| `examples/test-codeeditor.php` | Ffi::init() + show() before CodeEditor creation |
| `examples/test-debug-bridge.php` | Ffi::init() + show() before WebView creation |

### Phase 7d: ✅ CircleProgressBar 中心文字 + 颜色自定义
- **需求**：将进度百分比显示在圆环中心，字体大小随圆环尺寸自动缩放，支持自定义颜色
- **文字绘制**：参考 `monitor.php` 的 `label()` 模式 — `AttributedString` + `FontDescriptor` + `TextLayout(Center)` + `drawString()`
- **字体缩放**：`max(14.0, $innerDiameter * 0.10)` — 每次 draw() 调用时根据当前 Area 尺寸动态计算
- **颜色 API**：`setColor(float $r, float $g, float $b, float $a = 1.0)` + 静态 `TEXT_COLOR` 常量
- **厚度 API**：`getThickness()` 方法
- **布局变化**：移除外部 Label，百分比文字直接画在圆环中心
- **失败尝试记录**：
  - 0.45/0.48 填充比太大（144pt 字体填满 300px 环）
  - `extents()` 在 1e6 布局宽度下返回值不准确
  - 垂直偏移 `$cy - $fontSize * 0.50` 仍然偏下
  - 最终方案：`$cy - $fontSize / 2` + `DrawTextAlign::Center` + `$cx - $innerDiameter / 2` box 定位
- Files: `src/Widgets/CircleProgressBar.php`, `examples/all-components.php`, `examples/test-circle-progress.php`

### Phase 9: ✅ WebView/CodeEditor 创建顺序修复
- **根因**：`wvb_create()` 调用 `IsWindow(parent_hwnd)`，需要 `uiInit()` + 可见窗口
- **表现**：`test-codeeditor.php` 和 `test-debug-bridge.php` 运行后立即退出（无错误输出）
- **修复**：在创建 WebView 控件前调用 `Ffi::init()` + `$window->show()`
- **正确顺序**：`Ffi::init()` → `$window->show()` → `new CodeEditor/WebView()`
- **对比**：`all-components.php` 在按钮回调中创建（App::run() 已 init+show），`test-treeview.php` 已有正确顺序
- Files: `examples/test-codeeditor.php`, `examples/test-debug-bridge.php`

### 待办
- [ ] 确认 CircleProgressBar 字体大小和位置是否合适
- [ ] 清理 test_*.php 临时文件
