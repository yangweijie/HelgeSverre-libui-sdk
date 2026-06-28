# Findings: Windows 兼容性

## Area 控件在 Windows 上的限制

### 核心发现：padded Box 阻断 Area draw 回调
- 非 stretchy Area 在 padded Box 中 draw 回调**永远不触发**
- `Build::stretchy($area)` 能让 draw 回调正常触发
- 非滚动 Area + `setSize()` + `queueRedrawAll()` 从 timer 调用也不行
- 只有 stretchy 模式才能让 libui Windows 后端发起 paint

### ⚠️ 关键发现：不要在 stretchy Area 上调用 setSize()
- `Area` 构造函数已有内置 timer(0) 处理初始绘制
- 在 stretchy Area 上调用 `setSize()` 会**与 stretchy 容器冲突**，导致卡死
- 正确做法：依赖 stretchy 容器自动管理尺寸，draw 中用 `$params->areaWidth/Height`

### 正确用法
```php
// ✅ 正确 — stretchy Area 在 Box 中能绘制，无 setSize()
$box->appendStretchy($area);

// ✅ 正确 — Build::stretchy 包装
Build::hbox(Build::stretchy($area), ...);

// ❌ 错误 — 非 stretchy Area 在 padded Box 中不绘制
$box->append($area, false);

// ❌ 错误 — 在 stretchy Area 上调用 setSize() 导致卡死
$area->setSize(120, 120);
```

### 自绘控件必须用实际面积尺寸
- stretchy 后 Area 尺寸不再固定，draw 中不能硬编码
- 用 `$params->areaWidth` / `$params->areaHeight` 居中绘制

## PebView WebView2 架构

### 窗口层级
```
libui Window (parent_hwnd)
  └─ Bridge STATIC child (child_hwnd)
       └─ PebView webview_widget — 初始 0×0
            └─ WebView2 controller
```

### webview_widget 0×0 问题
- `win32_edge_engine` 在 `m_window` 内创建 `webview_widget` 初始 0×0
- 需要 `WM_SIZE` 触发 `resize_widget()`，但 STATIC 默认 WndProc 不转发
- 修复：bridge 中 `webview_create` 后用 `FindWindowExW` + `MoveWindow` + `SendMessage(WM_SIZE)`

### JS↔PHP 桥接
- macOS: `window.webkit.messageHandlers.__webview__.postMessage(msg)`
- Windows: `window.chrome.webview.postMessage(msg)`
- TreeView 的 `INIT_SCRIPT_POST` 需要平台自适应检测

## TableModel 问题
- `uiTableValueString()` 返回 `const char*`，PHP FFI 自动转为 PHP string
- `borrowedString()` 需要 `FFI\CData` 参数，类型不匹配
- 修复：直接用 `uiTableValueString()` 返回值，无需 `borrowedString()` 包装

## bridge 编译要求 (Windows)
```
gcc -shared webview_bridge_win.c PebView.dll -o webview_bridge.dll -luser32
```
- 必须链接 PebView.dll
- PebView.dll 位置：`vendor/kingbes/pebview/lib/windows/PebView.dll`

## Area timer 机制
- `Ffi::timer(0, fn)` 返回 `false` 可停止（一次性）
- `Ffi::timer(0, fn)` 返回 `true` 或无返回值则重复
- timer 在事件循环第一个 tick 触发，但 draw 回调需 Area 已在 widget tree 中且 stretchy

## CircleProgressBar 卡死根因
- **不要在 stretchy Area 上调用 `setSize()`** — 会与 stretchy 容器冲突导致卡死
- Area 构造函数已有内置 timer(0) 处理初始绘制，无需额外 timer
- 正确做法：依赖 stretchy 容器自动管理尺寸，draw 中用 `$params->areaWidth/Height`

## CircleProgressBar 可见性根因（非滚动 Area 无 preferred size）

### 问题
- 非滚动 `Area` 在 libui 布局系统中 **preferred size = 0**
- `uiAreaSetSize()` 对非滚动 Area 是 **no-op**
- 在 hbox 中：Area 高度 = max(area preferred=0, label preferred=17px) = 17px
- CircleProgressBar 在 17px 高度下 radius = min(w, 8.5) - 6 - 4 = -1.5 → 画不出

### 解决方案：minimum ring envelope + 布局拉伸
1. **draw() 中用 minimum ring envelope**：`$minDiameter = $this->thickness * 2 + 8; $diameter = max($minDiameter, min($w, $h) - 8);` — 任何尺寸都能画出可见的环
2. **外层 vbox 中 Group 设为 stretchy**：`Build::stretchy($groupCircle)` 让 Group 占满剩余垂直空间
3. **Group 内部用 vbox 而非 hbox**：`Build::vbox(Build::stretchy($area), $label)` 让 Area 获得 stretchy 垂直空间

### 正确布局模式
```php
// ✅ 外层：Group stretchy 在 vbox 中获取高度
$toggleControls = Build::vbox(
    ...,
    Build::stretchy($groupCircle),
    ...,
);

// ✅ 内层：Area stretchy 在 vbox 中获取高度
$groupCircle = Group::titled("Title:",
    Build::vbox(Build::stretchy($circleBar->root()), $label),
);

// ❌ 错误：hbox 中 Area 高度受 Label 限制（只有 17px）
Build::hbox(Build::stretchy($area), $label)
```

## CircleProgressBar 文字绘制（monitor.php 模式）

### drawString() API
```php
$ctx->drawString(
    $text,
    $font,                          // FontDescriptor
    Color::rgba($r, $g, $b, $a),    // 颜色
    $x,                             // layout box LEFT edge
    $y,                             // layout box TOP
    $width,                         // layout box width（用于 center 对齐）
    DrawTextAlign::Center,
);
```

### 正确的文字居中模式（参考 monitor.php label()）
```php
$fontSize = max(14.0, $innerDiameter * 0.10);
$font = new FontDescriptor('Arial', $fontSize);
$str = new AttributedString();
$str->append($text, Attribute::fromColor(Color::rgba(...$color)), Attribute::size($fontSize));
$layout = new TextLayout($str, $font, $innerDiameter, DrawTextAlign::Center);
$ctx->text($layout, $cx - $innerDiameter / 2, $cy - $fontSize / 2);
$layout->free();
```

### 关键发现
- `uiDrawText(ctx, layout, x, y)` 的 (x,y) 是 layout box 的**左上角**
- `DrawTextAlign::Center` 在 layout box 宽度内居中文本，box 本身需手动居中
- `TextLayout::extents()` 在极大宽度（1e6）下返回值不准确，不可靠
- `AttributedString` 需要同时指定 `Attribute::size()` 和 `FontDescriptor`（冗余但必要）
- 字体缩放公式 `max(14.0, $innerDiameter * 0.10)`：300px→30pt，1000px→100pt，140px→14pt(min)

## WebView/CodeEditor 创建需要 3 步初始化

### 根因
- `wvb_create()` 调用 `IsWindow(parent_hwnd)` — 需要有效的 HWND
- `uiControlHandle()` 返回的 HWND 在 `uiInit()` 之前无效
- bridge 返回 NULL → WebView 构造函数抛异常 → 进程退出

### 正确顺序（必须按此顺序）
```php
Ffi::init();                // 1. 初始化 libui (uiInit)
$window = new Window(...);
$window->setChild($layout);
$window->show();            // 2. 显示窗口 (HWND 生效)
$editor = new CodeEditor($window, ...);  // 3. 创建 WebView 控件
```

### 为什么 App::new()->run() 模式下不需要手动 Ffi::init()
`App::run()` 内部调用 `Ffi::init()` 然后 `$window->show()` — 所以在按钮回调中创建 CodeEditor 是安全的（事件循环已启动）。

### 对比
| 文件 | 顺序 | 结果 |
|------|------|------|
| `all-components.php` | `App::run()` 内部 init+show，CodeEditor 在回调中创建 | ✅ |
| `webview.php` | `Ffi::init()` + `$win->show()` 后创建 WebView | ✅ |
| `test-treeview.php` | `Ffi::init()` + `$window->show()` 后创建 TreeView | ✅ |
| `test-codeeditor.php` (修复后) | `Ffi::init()` + `$window->show()` 后创建 CodeEditor | ✅ |
| `test-debug-bridge.php` (修复后) | `Ffi::init()` + `$window->show()` 后创建 WebView | ✅ |

### 失败方案记录
| 方案 | 问题 |
|------|------|
| `innerDiameter * 0.45` | 144pt 字体填满 300px 环 |
| `min(D*0.48, D*0.85/(textLen*0.6))` | 仍然太大 |
| `extents()` + 1e6 宽度居中 | 返回值不准确 |
| 垂直偏移 `$cy - $fontSize * 0.50` | 文字偏下 |
| `$cy - $fontSize * 0.45` | 仍然偏下 |
| `DrawTextAlign::Center` 无 box 居中 | 文字偏右 |

## ContextMenu bridge 编译

### 需要手动编译
`context_menu.dll` 不在 git 中（与 webview_bridge.dll 一样），需要手动编译：
```bash
cd bridge && gcc -shared -o context_menu.dll context_menu_win.c -luser32
```

### 源文件修复
- `context_menu_win.c` 缺少 `#include <stdio.h>`（snprintf 隐式声明） — 已修复

### 示例脚本路径修复
- `test-context-menu.php` 硬编码 macOS `.dylib` 路径 — 改为 `match(PHP_OS_FAMILY)` 平台自适应

## Area 创建需要 Ffi::init() 在前

### 根因
- `new Area($delegate)` 调用 `Ffi::get()` 加载 C 库，然后调用 `uiNewArea()`
- `uiNewArea()` 需要 `uiInit()` 已调用，否则 C 级崩溃（无 PHP 异常）
- `App::new()->run()` 内部调用 `Ffi::init()`，但如果 Area 在 `run()` 之前创建就会崩溃

### 正确顺序
```php
Ffi::init();           // 1. 必须在任何控件创建前
$area = new Area(...); // 2. 现在安全
App::new()->run();     // 3. App::run() 内部也会调 Ffi::init()（幂等）
```

### 对比
| 文件 | 顺序 | 结果 |
|------|------|------|
| `all-components.php` | `Ffi::init()` 在第 65 行，Area 在回调中创建 | ✅ |
| `test-circle-progress.php` | `Ffi::init()` 在第 9 行 | ✅ |
| `test-context-menu-area.php` (修复后) | `Ffi::init()` 在第 23 行 | ✅ |

## Phase 11: 右键按钮映射差异

### 问题
`test-context-menu-area.php` 右键点击不触发 ContextMenu。代码使用 `isRightButtonDown()` 检查 `$this->down === 2`。

### 根因
在本 Windows 系统上，右键点击报告为 `down=3`（不是文档中的 `down=2`）。调试日志显示所有右键事件都是 `down=3`，左键是 `down=1`。

### 修复
- `test-context-menu-area.php`: 改为 `($event->down === 2 || $event->down === 3)` 检测右键
- `AreaMouseEvent.php`: 在 `$down` 属性注释中添加平台差异说明
- 桥接 DLL: 添加 `SetForegroundWindow()` + `GetForegroundWindow()` + `PostMessage(WM_NULL)` 修复 `TrackPopupMenu` 不显示问题

### 结果
调试日志确认 4 次成功的 `show()` 调用，返回索引 0、1、2、4（Red、Green、Blue、Disabled Item）。

### 注意
- `isRightButtonDown()` 方法保持 `down === 2` 不变（避免影响中间按钮检测）
- 使用此组件时需在 mouse 回调中手动检查 `down === 2 || down === 3`

## utopia-php/system Windows 不兼容

### 问题
`utopia-php/system` 库在 Windows 上多处抛异常：

| 方法 | 问题 | 原因 |
|------|------|------|
| `getArchEnum()` | `'AMD64' enum not found` | 正则 `/(x86*\|i386\|i686)/` 不匹配 `AMD64` |
| `getCPUCores()` | `'Windows NT not supported` | switch 检查 `'Windows'` 但 `php_uname('s')` 返回 `'Windows NT'` |
| `getMemoryTotal()` | `'Windows NT not supported` | 同上 |
| `isArch('aarch64')` | `'aarch64' not found` | 只接受 'x86'/'ppc'/'arm' |

### 修复策略
- 所有不兼容调用用 `try-catch` 包裹 + fallback
- `isX86()` 扩展：`System::isX86() || str_contains($arch, 'AMD64') || str_contains($arch, 'x86_64')`
- `isArm64()` 改为直接检查 arch 字符串（避免 `isArch()` 抛异常）
- `getCPUCores()` fallback: `shell_exec('echo %NUMBER_OF_PROCESSORS%')`

### 限制
- `getMemoryTotal()` 在 Windows 上不可用 — vendor 不支持
- `cpuUsage()` 在 Windows 上不可用 — vendor 不支持
- 需要上游修复或自行实现 Windows 版本

## Tray 托盘图标

### PebView DLL 路径
- Tray.php 中路径：`vendor/kingbes/pebview/lib/windows/x86_64/PebView.dll`
- **实际位置**：`vendor/kingbes/pebview/lib/windows/PebView.dll`（无 x86_64 子目录）
- Toast.dll 在 `windows/x86_64/Toast.dll`，PebView.dll 在 `windows/PebView.dll`

### FFI 参数传递
- `window_tray(const void *ptr, const char *icon)` 期望 `ptr` = HWND（void*）
- **错误写法**：`FFI::addr($winHandle)` 传递 void**（地址的地址）
- **正确写法**：直接传递 `$winHandle`

### HWND 获取方式
- `$window->handle()` 返回 `uiWindow*`（libui 内部结构体指针），**不是** Win32 HWND
- **正确方式**：`Ffi::get()->uiControlHandle($window->asControl())` 返回 `uintptr_t`（即 HWND）
- 需要 `\FFI::cast('void*', $hwnd)` 转为 void* 后传给 C 函数
- C 端 `window_show()` 需要 `SW_RESTORE`（非 `SW_SHOW`）才能恢复最小化窗口

### 初始化顺序
- Tray `attach()` 必须在 `App::new()->run()` 之前调用（创建隐藏托盘窗口）
- 但 `Ffi::init()` 必须在创建任何 Widget 之前调用
- 正确顺序：`Ffi::init()` → 创建 Window/Label → 创建 Tray → `attach()` → `App::run()`

## GlobalHotkey bridge

### DLL 编译
- `hotkey.dll` 不在 git 中（只有 macOS `hotkey.dylib`），需手动编译
- 编译命令：`gcc -shared -o bridge/hotkey.dll bridge/hotkey_win.c -luser32`
- `hotkey_win.c` 需要 `#include <stdbool.h>`（使用 `bool`/`true`/`false`）

### C API
- `hotkey_register(const char *key_combo, int id)` → 注册热键，返回 1=成功
- `hotkey_unregister(int id)` → 注销单个热键
- `hotkey_unregister_all()` → 注销所有热键
- `hotkey_poll()` → 从隐藏 HWND_MESSAGE 窗口 peek `WM_HOTKEY`，返回触发的热键 ID

### ⚠️ 关键发现：uiQuit() 不能在 Loop::repeat() 回调中直接调用
- `Loop::stop()` → `Ffi::quit()` → `uiQuit()` → `PostQuitMessage(0)` 投递 `WM_QUIT`
- 但在 `Loop::repeat()` 回调内部调用时，回调未返回，事件循环无法处理 `WM_QUIT`
- **修复**：用 `Ffi::timer(0, fn() => Ffi::quit())` 延迟到下一个事件循环 tick

### Windows 热键修饰符
- `Cmd`/`Command` 映射到 `MOD_WIN`（Windows 键），可能与系统快捷键冲突
- Windows 上建议用 `Ctrl+Shift` 代替 `Cmd+Shift`

### RegisterHotKey 行为
- `RegisterHotKey` 注册的是线程级热键，`WM_HOTKEY` 投递到注册线程的消息队列
- 隐藏 `HWND_MESSAGE` 窗口用于接收 `WM_HOTKEY`（C 代码中 `PeekMessage(g_hwnd, WM_HOTKEY, WM_HOTKEY, PM_REMOVE)`）
- 如果热键已被系统或其他应用占用，`RegisterHotKey` 失败返回 0

## Dialog 动态尺寸计算

### calcSize() 模式
```php
$charW = 7;  // 默认字体每字符约 7px
$chrome = 104; // 标题栏(28) + label padding(16) + button bar(36) + margins(24)
$lines = max(1, ceil(mb_strlen($message) * $charW / 280));
$height = $chrome + max(20, $lines * 20);
$width = max(240, 280);
if ($parent !== null) {
    [$pw] = $parent->getContentSize();
    $width = max(200, min($width, (int)($pw * 0.8)));
}
```

### 要点
- 280px 宽度 / 7px 每字符 ≈ 40 字符每行
- DialogPrompt 比 DialogConfirm 多一个 Entry(28px) + 额外 padding，chrome=140
- 有 parent 时 cap 在 80% 父窗口宽度，避免弹窗超出

## CircleProgressBar macOS 文字居中

### 问题
`TextLayout($str, $font, $width, DrawTextAlign::Center)` 在 macOS 上文字偏左。Windows 正常。

### 根因
macOS CoreText 渲染的文字实际宽度大于 layout box 的逻辑宽度，`DrawTextAlign::Center` 在偏大的 box 内居中，导致视觉偏移。

### 解决方案
用 `extents()` 测量实际文字尺寸，手动居中：
```php
$layout = new TextLayout($str, $font, $innerDiameter * 2, DrawTextAlign::Left);
[$textW, $textH] = $layout->extents();
$ctx->text($layout, $cx - $textW / 2, $cy - $textH / 2);
$layout->free();
```

### 关键发现
- `DrawTextAlign::Center` 在 macOS 不可靠 — 文字偏移
- `extents()` 返回真实渲染尺寸（用 Left + 宽 layout 测量）
- `TextLayout::extents()` 在极大宽度（1e6）下不准确 — 用 2× innerDiameter 足够
- 垂直居中用 `$cy - $textH / 2` 而非 `$cy - $fontSize / 2`（fontSize 不等于实际渲染高度）

## libui 内存泄漏排查

### 问题
退出时 `uiprivUninitAlloc()` 报告泄漏 3 个 uiButton + 3 个 uiSeparator。

### 根因
inline 临时对象（如 `new Button('X')` 直接传入 `Build::hbox()`）被 PHP GC 回收后，底层 C 控件成为孤儿。libui 在 `App::run()` 结束后检测到未销毁的控件。

### 尝试 1：`Control::__destruct()` — 失败
给 Control 类添加 `__destruct()` 调用 `uiControlDestroy()`。结果报错：
```
You cannot destroy a uiControl while it still has a parent.
```
**原因**：libui 管理父子所有权。子控件在容器（Box/Tab/Window）内时，不能单独调用 `uiControlDestroy()`。只有容器能销毁其子控件。

### 最终修复
移除 `__destruct()`，将所有 inline 临时对象提取为命名变量。命名变量使 PHP 对象存活到脚本结束，此时 libui 的清理机制（Window → Box → children 递归销毁）正常运行。

### 关键发现
- **libui 不允许销毁仍有父控件的子控件** — `uiControlDestroy()` 会报错
- **libui 的 Window 销毁会递归销毁所有子控件** — 不需要手动逐个销毁
- **PHP GC 回收 wrapper 对象不会触发 C 控件销毁** — 没有 `__destruct` 时 C 控件变孤儿
- **命名变量是防止 GC 过早回收的最简单方案** — 保持所有 UI 对象在作用域内直到脚本结束