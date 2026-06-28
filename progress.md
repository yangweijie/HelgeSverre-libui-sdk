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

### Phase 10: ✅ ContextMenu bridge DLL 编译
- **问题**：`test-context-menu.php` 报错 `Context menu bridge not found` — DLL 未编译
- **修复**：`gcc -shared -o bridge/context_menu.dll bridge/context_menu_win.c -luser32`（MinGW）
- **附带修复**：`context_menu_win.c` 缺少 `#include <stdio.h>`（snprintf 隐式声明警告）
- **附带修复**：`test-context-menu.php` 硬编码 macOS `.dylib` 路径，改为平台自适应 `match(PHP_OS_FAMILY)`
- Files: `bridge/context_menu.dll`（新建）, `bridge/context_menu_win.c`, `examples/test-context-menu.php`

### Phase 10b: ✅ ContextMenu + Area 示例修复
- **问题**：`test-context-menu-area.php` 自动终止，无输出
- **根因**：`new Area($delegate)` 在 `App::run()` 之前调用 → `uiNewArea()` 在 `uiInit()` 之前 → C 级崩溃
- **修复**：添加 `Ffi::init()` 在 Area 创建前；`Build::stretchy($area)` 确保 Area 有尺寸
- Files: `examples/test-context-menu-area.php`

### Phase 11: ✅ ContextMenu 右键按钮映射修复
- **问题**：`test-context-menu-area.php` 右键点击不触发菜单
- **根因**：本 Windows 系统右键 = `down=3`（非文档中的 `down=2`），`isRightButtonDown()` 检查 `down === 2` 不匹配
- **修复**：mouse 回调改为 `($event->down === 2 || $event->down === 3)` 检测右键
- **附带修复**：桥接 DLL 添加 `SetForegroundWindow()` + `PostMessage(WM_NULL)` 修复 TrackPopupMenu
- **验证**：调试日志确认 4 次成功 show() 调用（返回 0, 1, 2, 4）
- Files: `examples/test-context-menu-area.php`, `bridge/context_menu_win.c`, `bridge/context_menu.dll`, `patches/.../AreaMouseEvent.php`（注释更新）

### 待办
- [ ] 确认 CircleProgressBar 字体大小和位置是否合适
- [ ] 清理 test_*.php 临时文件
- [ ] 确认 test-global-hotkey.php Ctrl+Shift+Q 退出是否正常

### Phase 12: ✅ SystemInfo Windows 兼容性修复
- **问题**：`test-system-info.php` 报错 `'AMD64' enum not found` — `utopia-php/system` 不识别 Windows 架构
- **根因**：`getArchEnum()` 正则 `/(x86*|i386|i686)/` 不匹配 `AMD64`；`getCPUCores()` switch 检查 `'Windows'` 但 `php_uname('s')` 返回 `'Windows NT'`；`isArm64()`/`isArmV7()`/`isArmV8()` 方法不存在
- **修复**：
  - `getArchEnum()` try-catch + fallback 到原始 arch 字符串
  - `getCPUCores()` try-catch + fallback 到 `%NUMBER_OF_PROCESSORS%`
  - `isX86()` 扩展识别 `AMD64`/`x86_64`
  - `isArm64()` 改为直接检查 arch 字符串（避免 `isArch()` 抛异常）
  - 移除不存在的 `isArmV7()`/`isArmV8()` 方法
- **限制**：`getMemoryTotal()` 在 Windows 上返回 0（vendor 不支持），显示 "Unsupported" 警告
- Files: `src/System/SystemInfo.php`, `examples/test-system-info.php`

### Phase 13: ✅ Tray 托盘图标修复
- **问题**：`php85 examples/test-tray.php` 自动停止（无输出、无窗口）
- **根因**（3 个）：
  1. PebView DLL 路径错误：`windows/x86_64/PebView.dll` → 实际为 `windows/PebView.dll`
  2. `FFI::addr($winHandle)` 传递 `void**`（地址的地址）而非 `void*`（句柄值）
  3. 测试脚本：缺少 `Ffi::init()` 调用、双 `App::new()` 创建
- **修复**：
  - `Tray::ffi()` 路径改为 `$base . '/windows/PebView.dll'`
  - `Tray::attach()` 移除 `FFI::addr()` 包装，直接传递 `$winHandle`
  - 移除未使用的 `$winPtr` 属性
  - `test-tray.php` 添加 `Ffi::init()`，移除多余的 `App::new()`
- **验证**：脚本打印 "Tray icon created" 并保持运行（事件循环正常）
- Files: `src/System/Tray.php`, `examples/test-tray.php`

### Phase 13b: ✅ Tray Show Window 修复
- **问题**：托盘菜单 "Show Window" 点击后窗口不恢复显示
- **根因**：`$window->handle()` 返回 `uiWindow*`（libui 内部结构体指针），非 Win32 HWND。C 代码 `(HWND)ptr` 把 `uiWindow*` 当 HWND 用是错误的
- **修复**：
  - `Tray::showWindow()` 使用 `Ffi::get()->uiControlHandle($window->asControl())` 获取真正的 HWND
  - `Tray::attach()` 同样用 `uiControlHandle()` 获取 HWND 传给 `window_tray()`
  - C 源码 `window_show()` 改用 `SW_RESTORE` + `SetForegroundWindow()` 代替 `SW_SHOW`
- **验证**：最小化窗口后，右键托盘 → Show Window → 窗口正确恢复 ✅
- Files: `src/System/Tray.php`, `vendor/kingbes/pebview/source/window/window_win.c`

### Phase 14: ✅ GlobalHotkey bridge DLL 编译 + quit 修复
- **问题 1**：`php85 examples/test-global-hotkey.php` 立即退出
- **根因 1**：
  - `bridge/hotkey.dll` 不存在（只有 macOS `.dylib` 和 C 源码）
  - `hotkey_win.c` 缺少 `#include <stdbool.h>`（`bool`/`true`/`false` 未定义）
  - 测试脚本缺少 `Ffi::init()` 调用
- **修复 1**：
  - `hotkey_win.c` 添加 `#include <stdbool.h>`
  - MinGW 编译：`gcc -shared -o bridge/hotkey.dll bridge/hotkey_win.c -luser32`
  - `test-global-hotkey.php` 添加 `Ffi::init()` + `use Libui\Ffi`
- **问题 2**：`Cmd+Shift+Q` 不退出窗口，且之后 `Cmd+Shift+H` 也失效
- **根因 2**：
  - `Loop::stop()` → `Ffi::quit()` → `uiQuit()` 在 `Loop::repeat()` 回调内部调用
  - `uiQuit()` 投递 `WM_QUIT` 但当前回调未返回，事件循环无法处理 → quit 无效
  - `unregisterAll()` 成功 → 所有热键被注销 → `Cmd+Shift+H` 失效
  - `Cmd` 在 Windows 上 = `MOD_WIN`（Windows 键），可能与系统快捷键冲突
- **修复 2**：
  - 热键从 `Cmd+Shift+H/Q` 改为 `Ctrl+Shift+H/Q`（避免 Windows 键冲突）
  - 退出改用 `Ffi::timer(0, fn() => Ffi::quit())` 延迟到下一个事件循环 tick
- Files: `bridge/hotkey.dll`（编译）, `bridge/hotkey_win.c`, `examples/test-global-hotkey.php`
### Phase 15: ✅ DialogConfirm/DialogPrompt 动态尺寸
- **问题**：`ask()` 窗口宽高写死 360×140/160，短消息留白过多
- **修复**：新增 `calcSize()` 方法，根据 message 长度动态计算宽高
  - 宽度：min 240px，有 parent 时不超过父窗口 80%
  - 高度：chrome + label 行数 × 20px
- Files: `src/Dialogs/DialogConfirm.php`, `src/Dialogs/DialogPrompt.php`

### Phase 16: ✅ CircleProgressBar macOS 文字居中修复
- **问题**：macOS 上百分比文字偏左，不在圆环中心
- **根因**：`TextLayout` + `DrawTextAlign::Center` 在 macOS CoreText 下渲染偏移
- **修复**：用 `extents()` 测量实际文字宽高，手动 `(cx - textW/2, cy - textH/2)` 居中
- Files: `src/Widgets/CircleProgressBar.php`

### Phase 17: ✅ macOS 内存泄漏排查 + all-components.php 重构
- **问题**：退出时 libui 报泄漏 3 个 uiButton + 3 个 uiSeparator
- **根因**：inline 临时对象被 PHP GC 回收后，底层 C 控件成为孤儿
- **尝试 1（已回滚）**：给 `Control` 添加 `__destruct()` → `You cannot destroy a uiControl while it still has a parent`
- **最终修复**：移除 `__destruct()`，所有 inline 临时对象提取为命名变量，防止 GC 在事件循环期间过早回收
- Files: `examples/all-components.php`

### Phase 18: ✅ App.php + Control.php 补丁 — 真正修复内存泄漏
- **问题**：Phase 17 后仍有 `uiWindow` + `uiLabel` 泄漏
- **根因**：`App::run()` 在 `finally` 中先调 `Ffi::uninit()`（设 `initialized=false`），然后 PHP GC 才运行 `__destruct()`。此时 `isInitialized()` 为 false → 跳过销毁
- **修复 1**：`Control.php` 添加 `__destruct()` — 仅对 toplevel 控件调用 `destroy()`（子控件由父容器管理）
- **修复 2**：`App.php` 在 `Ffi::uninit()` 之前显式 `destroy()` 所有注册的 Window → libui 递归销毁子控件 → leak check 通过
- Files: `patches/helgesverre/libui/src/App.php`（新建）, `patches/helgesverre/libui/src/Control.php`

### Phase 19: ✅ CircleProgressBar Area 尺寸调试
- **问题**：macOS 上 CircleProgressBar 在 Tab 切换后尺寸异常（消失/偏移/重叠）
- **尝试 1**：非滚动 Area + `Build::stretchy()` → Area 撑满整个 Tab 内容区，圆环偏大
- **尝试 2**：非滚动 Area + 固定 ringSize 200×200 居中 → Tab 切换后 viewport 高度变为 0，圆环消失
- **尝试 3**：滚动 Area (`Area::scrolling`) + 固定内容尺寸 → 内容居中但 viewport 显示左上角
- **尝试 4**：非滚动 Area + `Tab::onSelected()` 强制 `queueRedrawAll()` → 绘制回调不触发
- **尝试 5**：`Ffi::timer(50)` 延迟重绘 → viewport 仍为 0×0
- **尝试 6**：`uiAreaSetSize()` 强制尺寸 → 报错 `You cannot call uiAreaSetSize() on a non-scrolling uiArea`
- **最终方案**：滚动 Area + `queueRedrawAll()` 定时器，draw 方法用固定 ringSize 居中（content 坐标系）
- **关键发现**：
  - `uiAreaSetSize()` 只能用于滚动 Area
  - `queueRedrawAll()` 在 macOS Tab 切换后不触发绘制回调，需 `Ffi::timer()` 延迟
  - Tab 的 `onSelected()` 回调签名是 `(Tab $tab)` 不是 `(int $index)`
  - macOS 上 Tab 切换后非滚动 Area 的 viewport 高度变为 0
- Files: `src/Widgets/CircleProgressBar.php`, `examples/all-components.php`

### Phase 20: ✅ all-components.php Tab 顺序 + Fields 独立示例
- **问题**：Fields tab 内容过窄导致窗口缩小，切换 tab 后无法恢复
- **修复**：将 Fields tab 从 all-components.php 移除，创建独立 `test-fields.php`
- **附带**：Custom tab 调整为第一个，CircleProgressBar 按钮恢复到布局中
- Files: `examples/all-components.php`, `examples/test-fields.php`（新建）

### Phase 21: ✅ SvgView 组件 — SVG 显示功能
- **问题**：需要在 libui 中显示 SVG 文件，用于后续 UI 美化
- **方案**：创建 `SvgView` 组件，基于 libui Area + DrawContext 绘制 SVG
- **安装依赖**：`kaareln/php-svg-path-data` 解析 SVG path `d` 属性
- **关键发现**：
  - 滚动 Area 的 viewport 报告 0×0，draw 坐标在 content 空间
  - `instanceof` 继承关系导致错误匹配（`Line extends Move`，`RelativeLine extends RelativeMove`）
  - SVG path data 库的迭代器返回命令顺序是**反的**，必须 `array_reverse()`
  - `<g>` 组的 fill/stroke 属性需传递给子元素（`parseElements()` 增加继承参数）
  - `<text>` 元素需要用 `drawString()` 而非 `fill()`/`stroke()`
- **支持的 SVG 元素**：`<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polygon>`, `<polyline>`, `<path>`, `<text>`, `<g>`
- **支持的 path 命令**：M/m, L/l, H/h, V/v, C/c, Q/q, A/a, Z
- Files: `src/Widgets/SvgView.php`（新建）, `examples/test-svg.php`（新建）

### Phase 22: ✅ Composer 构建脚本 + 库缺失提示
- **问题**：PebView 编译后库文件不在 git 中，用户运行 WebView 组件时报错不友好
- **修复 1**：`composer.json` 新增 `build`/`build:pebview`/`build:bridge` 脚本，自动检测平台调用对应编译命令
- **修复 2**：`WebView.php` 新增 `checkLibraries()` 方法，检测库文件是否存在，缺失时输出完整构建指引
- Files: `composer.json`, `src/WebView.php`

### Phase 23: ✅ Windows setWindowIcon 图标不显示修复
- **问题**：`setWindowIcon()` 在 Windows 上返回成功 (code=0) 但图标不显示
- **根因 3 个**（已修复 2 个，第 3 个本次修复）：
  1. PebView DLL 路径错误 `windows/x86_64/PebView.dll` → `windows/PebView.dll` ✅
  2. HWND 未转换：`uiControlHandle()` 返回 `uintptr_t` → `\FFI::cast('void*')` ✅
  3. **`DestroyIcon(hIcon)` 在 `WM_SETICON` 后立即销毁图标句柄** → 图标失效 ❌→✅
- **修复 3（本次）**：
  - `icon.c`：移除 `DestroyIcon(hIcon)` — `WM_SETICON` 存储句柄引用，应用必须保持图标存活
  - `icon.c`：添加 `WM_SETICON ICON_SMALL` — 任务栏常使用小图标
  - 重新编译 `PebView.dll`（75KB）
- **验证**：`test-set-icon.php` 返回 code 0（待用户视觉确认）
- Files: `vendor/kingbes/pebview/source/seticon/icon.c`, `vendor/kingbes/pebview/lib/windows/PebView.dll`
