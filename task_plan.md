# Task Plan: Windows 兼容性修复

## Goal
修复 HelgeSverre-libui-sdk 在 Windows 上的所有兼容性问题：WebView2 不渲染、JS↔PHP 桥接断裂、自绘控件不显示、TableModel 报错。

## Current Phase
Phase 14 完成 ✅ — GlobalHotkey bridge DLL 编译 + quit 修复

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

### Phase 9: WebView/CodeEditor 创建顺序修复 ✅
- **根因**：`wvb_create()` 需要 `uiInit()` + 有效 HWND
- **修复**：示例文件在创建 WebView 控件前调用 `Ffi::init()` + `$window->show()`
- Files: `examples/test-codeeditor.php`, `examples/test-debug-bridge.php`

### Phase 10: ContextMenu bridge DLL 编译 ✅
- **问题**：context_menu.dll 未编译，test-context-menu.php 报错
- **修复**：MinGW 编译 `context_menu_win.c` → `context_menu.dll`
- **附带**：添加 `#include <stdio.h>`，test 脚本路径改为平台自适应
- Files: `bridge/context_menu.dll`, `bridge/context_menu_win.c`, `examples/test-context-menu.php`

### Phase 10b: ContextMenu + Area 示例修复 ✅
- **问题**：`test-context-menu-area.php` 自动终止
- **根因**：Area 在 `Ffi::init()` 之前创建 → `uiNewArea()` C 级崩溃
- **修复**：添加 `Ffi::init()` + `Build::stretchy($area)`
- Files: `examples/test-context-menu-area.php`

### Phase 11: ContextMenu 右键按钮映射修复 ✅
- **问题**：右键点击不触发菜单
- **根因**：本系统右键 = `down=3`（非文档 `down=2`），`isRightButtonDown()` 不匹配
- **修复**：mouse 回调改为 `($event->down === 2 || $event->down === 3)`；桥接 DLL 添加 `SetForegroundWindow()`
- Files: `examples/test-context-menu-area.php`, `bridge/context_menu_win.c`, `bridge/context_menu.dll`

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| PebView 先于 bridge 加载 | bridge DLL 依赖 PebView 符号 |
| Widget 手动 resize | PebView widget 初始 0×0 |
| INIT_SCRIPT_POST 平台自适应 | macOS/Windows 不同桥接 |
| Area 必须 stretchy 才能 draw | libui Windows 后端限制 |
| 自绘 draw 用 `$params->areaWidth/Height` | stretchy 后 Area 尺寸变化，不能硬编码 |
| 右键检测用 `down === 2 \|\| down === 3` | 部分 Windows 系统右键报告为 button 3 |
| HWND 通过 `uiControlHandle()` 获取 | `$window->handle()` 返回 `uiWindow*` 非 HWND |

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
| CodeEditor 窗口不显示 | Ffi::init() + show() 在创建 WebView 控件前 |
| ContextMenu bridge 未编译 | MinGW 编译 context_menu_win.c → .dll |
| context_menu_win.c snprintf 警告 | 添加 `#include <stdio.h>` |
| ContextMenu 右键不触发 | 按钮映射 down=3 而非 down=2 + SetForegroundWindow |
| SystemInfo 'AMD64' enum not found | try-catch + fallback + 扩展 isX86() |
| SystemInfo getCPUCores Windows NT | try-catch + %NUMBER_OF_PROCESSORS% fallback |
| SystemInfo isArm64/isArmV7 方法不存在 | 改为 arch 字符串直接检查 |
| Tray Show Window 不恢复 | `uiControlHandle()` 获取真正 HWND + `SW_RESTORE` |
| GlobalHotkey DLL 未编译 | MinGW 编译 `hotkey_win.c` + `stdbool.h` |
| GlobalHotkey quit 不生效 | `Ffi::timer(0, ...)` 延迟退出 + `Ctrl+Shift` 避免系统冲突 |

### Phase 13: Tray 托盘图标修复 ✅
- **PebView DLL 路径错误**：`windows/x86_64/PebView.dll` → `windows/PebView.dll`
- **FFI 参数传递错误**：`FFI::addr($winHandle)` 传递 void** 而非 void*
- **测试脚本初始化顺序**：添加 `Ffi::init()` + 移除双 `App::new()`

### Phase 13b: Tray Show Window 修复 ✅
- **HWND 获取错误**：`$window->handle()` 返回 `uiWindow*` 非 HWND，改用 `uiControlHandle($window->asControl())`
- **window_show() API**：C 端 `SW_SHOW` → `SW_RESTORE` + `SetForegroundWindow()` 恢复最小化窗口
- **FFI cdef**：添加 `window_show()` 声明，Tray 新增 `showWindow()` 方法

### Phase 14: GlobalHotkey bridge DLL 编译 + quit 修复 ✅
- **bridge/hotkey.dll 未编译**：MinGW 编译 `hotkey_win.c` → `hotkey.dll`（`-luser32`）
- **stdbool.h 缺失**：`hotkey_win.c` 添加 `#include <stdbool.h>`
- **测试脚本缺少 Ffi::init()**：添加初始化调用
- **Loop::stop() 在回调中无效**：`uiQuit()` 投递 WM_QUIT 但回调未返回，事件循环无法处理 → 改用 `Ffi::timer(0, ...)` 延迟退出
- **Cmd 在 Windows = MOD_WIN**：与系统快捷键冲突 → 改用 `Ctrl+Shift` 组合
- Files: `bridge/hotkey.dll`, `bridge/hotkey_win.c`, `examples/test-global-hotkey.php`
### Phase 15: DialogConfirm/DialogPrompt 动态尺寸 ✅
- 修复 `ask()` 写死 360×140/160 → `calcSize()` 根据 message 长度动态计算
- Files: `src/Dialogs/DialogConfirm.php`, `src/Dialogs/DialogPrompt.php`

### Phase 16: CircleProgressBar macOS 文字居中 ✅
- 修复 macOS CoreText 下 `DrawTextAlign::Center` 偏移 → `extents()` 手动居中
- Files: `src/Widgets/CircleProgressBar.php`

### Phase 17: macOS 内存泄漏排查 + all-components.php 重构 ✅
- 尝试 `Control::__destruct()` → 回滚（libui 禁止销毁仍有父控件的子控件）
- 最终方案：所有 inline 临时对象提取为命名变量，防止 GC 过早回收
- Files: `examples/all-components.php`

### Phase 18: App.php + Control.php 补丁 — 真正修复内存泄漏 ✅
- `Control::__destruct()` — 仅对 toplevel 控件调用 `destroy()`
- `App::run()` — 在 `Ffi::uninit()` 前显式 destroy 所有 Window
- Files: `patches/helgesverre/libui/src/App.php`, `patches/helgesverre/libui/src/Control.php`

### Phase 19: CircleProgressBar Area 尺寸调试 ✅
- 6 次尝试解决 macOS Tab 切换后 Area 尺寸异常
- 最终方案：滚动 Area + 定时器重绘 + 固定 ringSize 居中
- Files: `src/Widgets/CircleProgressBar.php`, `examples/all-components.php`

### Phase 20: all-components.php Tab 顺序 + Fields 独立示例 ✅
- Fields tab 移至独立 `test-fields.php`，Custom tab 调为第一个
- Files: `examples/all-components.php`, `examples/test-fields.php`

### Phase 21: SvgView 组件 — SVG 显示功能 ✅
- 基于 libui Area + DrawContext + kaareln/php-svg-path-data 实现
- 支持 rect/circle/ellipse/line/polygon/polyline/path/text/g 元素
- path 命令：M/L/H/V/C/Q/A/Z（含相对坐标）
- 关键修复：instanceof 继承顺序、命令数组反转、`<g>` 属性继承
- Files: `src/Widgets/SvgView.php`, `examples/test-svg.php`

### Phase 22: Composer 构建脚本 + 库缺失提示 ✅
- `composer.json` 新增 `build`/`build:pebview`/`build:bridge` 脚本
- `WebView.php` 新增 `checkLibraries()` 缺失提示
- Files: `composer.json`, `src/WebView.php`

### Phase 23: Windows setWindowIcon 修复 ✅
- **问题**：`setWindowIcon()` 在 Windows 上返回成功但图标不显示
- **根因 3 个**：
  1. PebView DLL 路径错误：`windows/x86_64/PebView.dll` → `windows/PebView.dll`
  2. HWND 未转换：`uiControlHandle()` 返回 `uintptr_t`，需 `\FFI::cast('void*')`
  3. **`DestroyIcon(hIcon)` 在 `WM_SETICON` 后立即销毁句柄** → 图标失效
- **修复**：
  - `Window.php` DLL 路径 + HWND 转换
  - `all-components.php` Windows 用 `.ico` 格式
  - `icon.c`：移除 `DestroyIcon`，添加 `ICON_SMALL`
  - 重新编译 `PebView.dll`
- **验证**：用户确认窗口图标正常显示 ✅
- Files: `patches/helgesverre/libui/src/Window.php`, `vendor/kingbes/pebview/source/seticon/icon.c`, `vendor/kingbes/pebview/lib/windows/PebView.dll`, `examples/all-components.php`, `examples/test-set-icon.php`
