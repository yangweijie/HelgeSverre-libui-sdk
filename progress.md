# 进度日志 (Progress Log)

## 2026-07-01 — Tetris.app 闪退与内存泄漏修复

### Phase 29c: ✅ Tetris.app 闪退分析
- **问题 1**：启动闪退 "Cannot redeclare class Libui\Ffi" — PHP 8.5 `use FFI;` 语义变化 → 移除 `use FFI;`
- **问题 2**：关闭时 SIGTRAP — drawString 内存泄漏 + GC 不充分 → 显式 `free()` + 三次 GC
- **问题 3**：tokenizer 缺失 — 错误处理器崩溃 → 重建 micro.sfx 添加 tokenizer,filter

### Phase 29d: ✅ micro.sfx 重建
- SPC v3 构建：`ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter`
- 验证：`tokenizer:YES`, `filter:YES`

### Phase 29f: ✅ 补丁审查 + uiLabel 泄漏修复
- **发现 bug**：`markExternallyClosed()` 调用 `unset($this->handle)` 阻断 destroy 循环
- **修复**：保留 handle + `isExternallyClosed()` 守卫防双重释放

### Phase 29g-29h: ✅ tetris.php 清理
- 移除 onClosing 辅助代码（验证非必需）
- 删除 `$nextLabel`、局部 label 变量、冗余 `Ffi::init()`

### Phase 29i: ✅ 移除非必需补丁
- 删除 `patches/composer/ClassLoader.php`

### 文档更新
- `docs/{en,zh}/guide/patches.md` — 新增 Ffi/App/Control 条目
- `docs/{en,zh}/examples.md` — 扩展列表添加 tokenizer,filter
- `scripts/install-spc.bat` — SPC v3 语法 + tokenizer,filter

---

## 2026-07-07 晚（拖拽选牌功能）

### 本次完成
- [x] **拖拽连选/连消选牌**：重写 `onClick()` 支持长按左键拖拽。
  - 关键：libui `AreaMouseEvent` 拖拽移动时 `down=0` 但 `held` 保持按钮掩码 → 用 `down!==0 || held!==0` 判按住，`up!==0` 判松开。
  - 拖拽起点以首张牌当前状态决定模式：未选中 → 连选；已选中 → 连消。
  - `applyDrag()` 用 `dragTouched` 去重，单次拖拽每张牌只处理一次，避免闪烁。
  - 单点（按下+松开不移动）仍等价一次 toggle，向后兼容。
  - 移除死代码 `toggleSelect()`；`newGame()` 重置拖拽状态。
  - 新增 2 个 Pest 测试（拖拽连选/连消、单点 toggle）。**19 项全过**。php85 -l 通过。

## 2026-07-07 早（GUI 修复收尾 + 规划文件初始化）

### 本次完成
- [x] **日志乱码修复**（叫地主阶段）：`drawLog()` 中 `substr` → `mb_substr(..., 'UTF-8')`，截断 24→28 字符，按 UTF-8 字符边界切，彻底消除从汉字中间劈开产生的非法序列乱码（显示为 ♣♣ 等）。php85 -l 通过。
- [x] **初始化 planning-with-files 规划体系**：创建 `task_plan.md` / `findings.md` / `progress.md`，将前序多轮 GUI 修复（选将界面、主循环、AI 跟牌、卡牌 hit-test、结算居中、日志位置、托管功能、跨平台字体、窗口居中）系统归档。

### 验证
- `php85 -l src/Games/OnePieceDoudizhu/GameController.php` → No syntax errors
- 关键特性代码核验全部在位：mb_substr(L1261)、autoPlay(18处)、uiFontFamily(L33)、lastShownPlay(5处)、window->centered()(example L91)
- Pest 17 项测试（历史通过，本次未重跑因环境路径解析问题；纯数值/字符截断改动不影响逻辑）

### 待用户验证
- 运行 `php85 examples/onepiece-doudizhu.php` 截图确认：① 叫地主日志中文正常无乱码 ② 结算文字精确水平居中 ③ 日志不与出牌区/手牌重叠 ④ 托管延迟约 1s 且跨局自动取消

### 历史阶段回顾（已在 task_plan.md 汇总）
1. 核心设计 + 逻辑（Game.php）
2. 程序化 WAV 音效（scripts/gen-sfx.php）
3. GUI 框架 + 选将/对战界面
4. GUI 运行时多轮截图驱动修复（见 task_plan.md Errors 表）
5. 跨平台字体 + 窗口居中 + 按钮布局（重新选将置左、主按钮居中）
6. mb_substr 日志乱码修复（本次）

---

## 2026-07-11 — 三大子系统（图表 / 渲染引擎 / 布局引擎+自绘控件）

### 图表组件（Chart Component）
- **规划**：`.planning/2026-07-11-chart-component/`
- **10 阶段全部完成**：核心数据模型 → ChartRenderer / LineRenderer / BarRenderer / PieRenderer → Chart Area 组件 → chart-demo.php + 24 测试 → 拖拽交互修复 → 柱状标签/ tooltip / 主题 → tooltip 样式打磨 → 文档 → Color::lerp 主题动画 → tooltip 箭头 + 上色重构
- **测试**：24 项全部通过
- **未 commit**

### 渲染引擎（Rendering Engine）
- **规划**：`.planning/2026-07-11-rendering-engine/`（活跃中）
- **Phase 1**：RenderCommand / CommandExecutor / RenderCommandList + CircleProgressDelegate 提取到 Rendering 命名空间，8 测试
- **Phase 2**：DesignTokens 不可变主题系统 + CircleProgressBar / ToggleSwitch 主题化，6 测试
- **Phase 3**：WidgetRenderer 注册表（ButtonRenderer / CardRenderer）+ RendererButton 复合控件，12 测试
- **测试**：Phase 1-3 共 26 项全部通过
- **未 commit**

### 布局引擎 + 全自绘控件系统（Layout Engine + Full Custom Widget System）
- **规划**：`.planning/2026-07-11-layout-engine/`
- **Phase 4**：Flexbox 布局（LayoutStyle / LayoutNode / FlexLayout）16 测试
- **Phase 5**：Grid 布局（GridTrack / GridStyle / GridLayout）7 测试
- **Phase 6**：Surface 画布控件（1056 行）— 单一 Area 驱动 FlexLayout + RendererRegistry 绘制 + 鼠标路由
- **Phase 7**：扩展 WidgetRenderer（Checkbox / Radio / Slider / Progress / TextField / Select）9 测试
- **Phase 8**：事件系统（PointerEvent / KeyboardEvent / FocusManager）测试通过
- **Phase 9**：无障碍语义（WidgetRole / SemanticsNode）4 测试
- **Phase 10**：DesignTokens 扩展（hover/disabled wash、focus ring、hairline、DARK 主题）4 测试
- **额外控件**：BreadcrumbControl, ComboboxControl, DialogControl, DrawerControl, DropdownMenuControl, ListControl, PaginationControl, PopoverControl, ScrollViewControl, SearchFieldControl, SheetControl, TabControl, TableControl, TextAreaControl, RendererButton
- **示例**：renderer-button-demo.php, surface-controls-demo.php, surface-demo.php
- **修改**：CircleProgressBar.php / ToggleSwitch.php → DesignTokens；SeparatorLine.php → 移除 destructor；AGENTS.md → leak prevention 文档
- **未 commit**

### 修改的现有文件
- `CircleProgressBar.php` — DesignTokens 主题接入
- `ToggleSwitch.php` — DesignTokens 主题接入
- `SeparatorLine.php` — 移除 destructor（Composite GC 陷阱）
- `AGENTS.md` — 内存泄漏预防文档
---

## Session: 2026-07-12 — 4 个子系统实施

### Phase A: ✅ DesignTokens 扩展
- **范围**：扩展 `src/Rendering/DesignTokens.php` — 新增 typography（family/size/weight/lineHeight × 8 角色）、spacing（gap × 5 + padding × 3）、stroke（thin/default/thick）、elevation（none/low/medium/high）
- **迁移**：15 个 WidgetRenderer 文件从 `new FontDescriptor('Arial', $SIZE)` → `$tokens->font($SIZE)`
- **新增方法**：`font($size): FontDescriptor`, `spacing(): array`, `elevation(): array` + 快捷字体（headingFont, bodyFont, captionFont, labelFont, inputFont）
- **修复**：CircleProgressDelegate.php `$tokens` → `$this->tokens`
- **验证**：所有非 FFI 测试通过，WidgetRenderer 目录零残留 `new FontDescriptor('Arial', ...)`

### Phase B: ✅ 统一 CLI 工具链
- **创建**：`bin/ui2`（469 行）— 10 个子命令（build:phar/build:binary/build:pebview/build:bridge/install:spc/install:opencode/check/init/info/list）
- **composer.json**：添加 `"bin": ["bin/ui2"]`
- **验证**：`php bin/ui2 check/list/info` 正常输出

### Phase C: ✅ UI 快照测试
- **创建**：`tests/Helpers/Snapshot.php` — 轻量 JSON 快照断言（首次运行写基线，后续运行对比）
- **创建**：`tests/SnapshotTest.php` — 4 个测试（DesignTokens default/dark tree，SystemInfo static properties）
- **基线**：`tests/__snapshots__/` 下 3 个基线文件（357 行）
- **验证**：4/4 通过（首次创建 + 第二次对比）

### Phase D: ✅ Capability 守卫系统
- **创建**：`src/System/Capability.php`（接口）+ `CapabilityRegistry.php`（单例）+ `CapabilityException.php`
- **5 个实现**：AudioCapability（bridge/audio.dylib）、TrayCapability（PebView）、HotkeyCapability（bridge/hotkey.dylib）、SystemInfoCapability、ProcessCapability
- **验证**：`tests/CapabilityTest.php` — 23/23 通过

### 统计
| 指标 | Phase A | Phase B | Phase C | Phase D | 合计 |
|------|---------|---------|---------|---------|------|
| 新文件 | 0 | 1 | 4 | 8 | 13 |
| 修改文件 | 16 | 1 | 0 | 0 | 17 |
| 添加行 | ~120 | 469 | 220 | 480 | ~1289 |
| 测试 | — | — | 4 | 23 | 27 |

*规划文件遵循 planning-with-files 技能：task_plan.md=阶段追踪，findings.md=技术发现，progress.md=会话日志。子工作流有独立的 `.planning/` 子目录规划文件。*

---

## Session: 2026-07-12 — ImageControl / AvatarControl GD 像素提取修复

### 问题背景
- `ImageControl::fromFile()` 和 `fromPng()` 加载 PNG 后显示内容但有严重噪声/颜色错误
- `AvatarControl::fromFile()` 同样显示橙色而非蓝色
- 像素数据提取的 R/B 通道顺序错误

### 根因分析
GD `imagecolorat()` 返回 `0xAARRGGBB` 格式：
- Bits 0-7: Blue
- Bits 8-15: Green  
- Bits 16-23: Red
- Bits 24-31: Alpha（0=不透明，127=透明）

**原始代码 R/B 通道交换**：提取 bits 0-7 作为 R，bits 16-23 作为 B → 颜色反转

**Alpha 反转**：GD alpha 0=不透明，渲染器 alpha 1.0=不透明 → 需要 `1.0 - alpha/127.0`

### 修复内容

#### 1. `ImageControl::fromPng()` 和 `fromFile()` (src/Widgets/ImageControl.php)
```php
$rgba = \imagecolorat($im, $x, $y);
// GD imagecolorat() returns 0xAARRGGBB: bits 16-23=R, 8-15=G, 0-7=B, 24-31=A
$pixels[] = (($rgba >> 16) & 0xFF) / 255.0;  // R
$pixels[] = (($rgba >> 8) & 0xFF) / 255.0;   // G
$pixels[] = ($rgba & 0xFF) / 255.0;           // B
$pixels[] = 1.0 - (($rgba >> 24) & 0x7F) / 127.0; // inverted alpha
```

#### 2. `AvatarControl::fromGdImage()` (src/Widgets/AvatarControl.php)
- 同样修复 R/B 交换
- 添加 `\imagealphablending($im, false)` 防止 GD 默认混合黑色背景

#### 3. Alpha 混合修复
- 在三个位置添加 `\imagealphablending($im, false)`：
  - `ImageControl::fromPng()` line ~85
  - `ImageControl::fromFile()` line ~143
  - `AvatarControl::fromGdImage()` line ~135

#### 4. Demo 更新 (examples/surface-controls-demo.php)
- 当 GD 可用时使用 `ImageControl::fromFile()` 加载 `assets/app-icon.png`
- 当 GD 可用时使用 `AvatarControl::fromFile()` 加载同一图标
- GD 不可用时回退到渐变球像素生成
- 标签显示使用的方法：`fromFile(app-icon.png)` 或 `像素生成（GD 不可用）`

### 验证
- `assets/app-icon.png`：256×256 蓝色方块白色 "U2" 文字
- 像素诊断：所有像素完全不透明（A=0），角落像素 R=37,G=99,B=235（蓝色）正确
- 渲染管线验证：`CommandExecutor::drawSampledPixels()` 读取 RGBA 顺序正确
- AvatarControl `fromPng()` 和 `fromFile()` 均委托给 `fromGdImage()`

### 文件变更
- `src/Widgets/ImageControl.php` — R/B 修复 + imagealphablending(false)
- `src/Widgets/AvatarControl.php` — R/B 修复 + imagealphablending(false)
- `examples/surface-controls-demo.php` — fromFile 演示

### 技术发现
- GD `imagecolorat()` 返回 `0xAARRGGBB`（R=bits16-23, G=bits8-15, B=bits0-7, A=bits24-31）
- GD alpha: 0=不透明，127=透明（与标准相反）
- SVG 不支持 via GD，需使用 `SvgView` 控件（`src/Widgets/SvgView.php`）

---

## Session: 2026-07-12 — DSL + State Management（Native SDK #1, #2）

### Phase G: ✅ 状态管理（Elm Architecture）
- **创建 5 个文件**：
  - `src/State/Model.php` — `Model` 空标记接口
  - `src/State/Msg.php` — `Msg` 空标记接口（UnitEnum）
  - `src/State/Effect.php` — 抽象副作用基类
  - `src/State/UpdateResult.php` — Model + Effect[] 返回类型
  - `src/State/AppRuntime.php` — 持有 Model + Update 函数，dispatch() 返回新 Model
- **设计**：Elm 架构映射到 PHP — Model(readonly class) / Msg(UnitEnum) / Update(纯函数) / AppRuntime(调度器)
- **测试**：`tests/State/CounterModelTest.php` — CounterModel + CounterMsg enum + counterUpdate 函数的 8 个测试
- **Demo 集成**：`examples/surface-controls-demo.php` — "Elm 风格 Counter" 区块，onClick → dispatch Msg → update spec → redraw
- **修复**：LabelSpec 无 `$weight` 参数 → 移除所有 `weight:` 调用

### Phase H: ✅ UI 声明（DSL via .native 标记）
- **创建 3 个文件**：
  - `src/Compiler/NativeException.php` — 解析异常（extends RuntimeException）
  - `src/Compiler/NativeLoader.php` — .native XML → LayoutNode 编译器（SimpleXML + ReflectionClass 属性→构造函数参数映射，自动类型转换）
  - `examples/counter.native` — 卡片 + Column + Label + Row + Button 的 Elm 计数器
- **支持 19 个 WidgetSpec 子类**：ButtonSpec, LabelSpec, CheckboxSpec, SliderSpec, ProgressSpec, RadioSpec, SelectSpec, PanelSpec, CardSpec, ScrollViewSpec, TextFieldSpec, SearchFieldSpec, DialogSpec, DrawerSpec, SheetSpec, PopoverSpec, BreadcrumbSpec, DropdownMenuSpec, ListSpec
- **特殊处理**：ScrollView → viewport row(ScrollViewSpec) + content column
- **明确排除**：ImageSpec（像素数据是运行时动态的）
- **Demo 集成**：`examples/surface-controls-demo.php` — "DSL 声明式 Counter" 区块，NativeLoader::load() + DFS 查找 Label 节点 + onClick dispatch CounterMsg

### 全部文件验证
- 所有新建和修改文件通过 `php -l` 语法检查

---

## 2026-07-12 — TextArea IME 中文输入回显修复

### 问题
Surface 的 TextAreaControl 输入中文/数字时，IME 候选词选择后文本不显示在多行文本框中。

### 诊断过程
1. **IME 代理工作正常**：`imeBridgeFfi=yes`，`text="a啊123c词"` 正确捕获 Unicode 输入
2. **TextArea 始终渲染空**：`withState: TextAreaSpec value=""`，`TextAreaRenderer: value=""`
3. **"checking cond" 调试行未出现**：说明 IME onChanged 回调在 line 503-533 之间因 segfault 静默死亡
4. **添加逐行调试**：发现 `ime_get_text_view()` 返回空 FFI CData（NULL 指针）
5. **根因**：`(int) $text_view_ptr === 0` → NULL 指针传给 `ime_is_composing()` → segfault

### 修复（三重 bug）

#### 1. IME onChanged segfault 修复 (Surface.php)
```php
// Before (always true for FFI NULL CData):
if ($text_view_ptr !== null && ...)  // segfaults

// After (checks actual pointer value):
$isNull = (int) $text_view_ptr === 0;
if (!$isNull && $this->imeBridgeFfi->ime_is_composing($text_view_ptr) === 0) {
```

#### 2. `withState()` 丢失 control 回引用 (Surface.php)
```php
// Before: new TextAreaSpec(... // 无 control, 用 stale $spec->value
// After:
$control = $spec->control;
$value = $control !== null ? $control->getValue() : $spec->value;
return new TextAreaSpec(
    value: $value,
    cursor: $control !== null ? $control->getCursor() : $spec->cursor,
    control: $control,  // 保留回引用
    // ...
);
```

#### 3. TextAreaControl 添加 getCursor() (TextAreaControl.php)
```php
public function getCursor(): int
{
    return $this->cursor;
}
```

### 文件变更
- `src/Widgets/Surface.php` — withState 修复 + NULL 指针检查 + 逐行调试日志
- `src/Widgets/TextAreaControl.php` — getCursor() 方法
- `src/Rendering/WidgetRenderer/TextAreaSpec.php` — $control 回引用（已存在）

### 验证
- `php -l` 语法检查通过（所有修改文件）
- **待手动验证**：`php85 examples/surface-controls-demo.php` → 输入中文/数字 → 观察文本是否回显

---

## Session: 2026-07-13 — TextArea IME 调试（placeholder 未消失）

### 问题
用户报告：能打字（中文显示在 NSTextView 覆盖层上），但 placeholder 仍不消失。

### 诊断
1. **IME 代理正常**：NSTextView 接收输入，中文在覆盖层显示
2. **spec 未更新**：`withState()` 返回的 `TextAreaSpec` 中 `value=""`，导致 `TextAreaRenderer` 渲染 placeholder
3. **callback 未被触发**：`ime_textViewDidChange` 的 `NSLog` 日志未输出 → C 端 observer 未被触发
4. **根因**：`$notifyCallback` 是 `handleImeFocus()` 的局部变量，函数返回后 PHP GC 回收了 FFI callback → C 端调用无效函数指针

### 修复（callback GC）
- 将 callback CData 和 closure 提升为 `Surface` 类的实例属性：
  - `$this->imeNotifyCallback` / `$this->imeNotifyFn`
  - `$this->imeTabCallback` / `$this->imeTabFn`
- `detachImeTextview()` 中统一清理四个属性
- `php85 -l` 全部通过

### 待用户验证
运行 `php85 examples/surface-controls-demo.php` → 点击 TextArea → 输入中文 → 观察：
1. placeholder 是否消失（value 更新成功）
2. stderr 日志确认 callback 被触发（`[Surface] IME notifyFn called:`）
3. 中文正常显示在 TextArea 中

### 调试日志（已添加，待用户运行后移除）
- `bridge/ime_bridge.m`: `NSLog` 在 `ime_textViewDidChange` 入口、早退分支、callback 调用前
- `src/Widgets/Surface.php`: `fwrite(STDERR)` 在 `handleImeFocus`、`notifyFn` 各关键位置
- `src/Widgets/TextAreaControl.php`: `fwrite(STDERR)` 在 `setValue`、`afterEdit`、`syncSpec`
- `src/Rendering/WidgetRenderer/TextAreaRenderer.php`: `fwrite(STDERR)` 在 `render` 入口

### 当前未 commit 的修改
- `bridge/ime_bridge.m` — block-based observer + 三重 debug logging
- `bridge/ime_bridge.dylib` — 最新编译（01:55）
- `src/Widgets/Surface.php` — 重写 `handleImeFocus` + debug logging + callback GC 修复
- `src/Widgets/TextAreaControl.php` — `getCursor`/`setCursor`/`setValueWithCursor` + debug logging
- `src/Rendering/WidgetRenderer/TextAreaRenderer.php` — debug logging

---

## Session: 2026-07-13 — 表单字段 IME 覆盖层「幽灵重叠」修复（searchField / textField）

### 问题链（用户截图 + 日志驱动，4 轮迭代）
表单字段（searchField "sf"、textField "tf"）在 Surface 上用浮动 NSTextView 覆盖层承载中文输入。
发现四类互相叠加的现象：
1. **销毁未执行 → 幽灵重叠**：sf 输入 → 滚动（无重叠）→ 点 tf（重叠）→ 回 sf（仍重叠）
2. **首焦不可见**：sf 第一次输入看不见，切到 tf 再切回才看到
3. **输入字比显示字大**：覆盖层字号与渲染字号不一致
4. **滚动跟随 + 重叠**：滚动时覆盖层随内容一起滚，且出现重叠

### 诊断关键（scroll7 / scroll8 / scroll9.log）
- `NSLog` 速率限制丢弃诊断日志 → 改用 `fprintf(stderr)`（与 PHP `fwrite(STDERR)` 同流，可靠）
- **scroll8.log 决定性证据**：`BEFORE has_textview=1` 已打印，但**无 `enter`/`AFTER`** → `ime_destroy_textview` 从未执行
- 阻塞点：`detachImeTextview()` 中在 destroy 之前调用的 `ime_clear_textview_first_responder` 会**卡死 / 重入** AppKit focus 机制，导致后续 destroy 永远跑不到
- create 时 `ime_create_textview` 有 "create swept 1 stray" 日志（line 1603）→ 存在残留在父视图树上的旧覆盖层

### 修复（四重）
#### 1. 字号参数化（消除「输入比显示大」）
- `bridge/ime_bridge.m` 签名扩展：`ime_create_textview(..., double font_size, const char* initial_text)`
- 桥内：`CGFloat fs = (font_size>0)?font_size:14.0; NSFont* font=[NSFont systemFontOfSize:fs];` + `setFont:` + `setTypingAttributes:@{NSFontAttributeName:font}`
- PHP 侧传递真实字号：`$fontSize = min($innerH*0.5, 14.0);`（与 `TextFieldRenderer/SearchFieldRenderer` 的 `min($height*0.5,14.0)` 一致）
- **根因**：桥内硬编码 14，短字段实际 <14 → 输入比显示大

#### 2. 销毁顺序重排 + 移除 first-responder 调用（消除「幽灵重叠」）
- `detachImeTextview()` 改为：**先**在隔离 try/catch 中 destroy（BEFORE/AFTER `ime_has_textview()` + `fwrite`/`fflush`），**后**清理 notify/tab callback
- 移除 `ime_clear_textview_first_responder`（`removeFromSuperview` 已隐含 resign，单独调用会重入 focus 机制卡死）

#### 3. 递归整窗清扫（消除「残留 ghost」）
- `collectImeViews(NSView* root, NSMutableArray* out)` 递归收集某根下所有 `IMENSTextView`
- `ime_destroy_textview` 重写：从存活 view 的 window.contentView 派生 sweepRoot（回退 superview / g_ime_parent_view），先移除已知全局 view，再递归清扫子树，强制 `setNeedsDisplay` + `displayIfNeeded` + `CATransaction flush`
- create 时 stray sweep 同样走 `collectImeViews`

#### 4. 逐帧重定位 + 首焦可见 + typingAttributes 统一
- `Surface::draw()` 末尾每帧调用 `$this->surface->repositionImeOverlay();`（修复首焦不可见 + 滚动跟随）
- **最后一项不一致（字符高度）**：初始 attributed string 无 font 属性，typing 属性有 → 历史存储字 vs 新输入字高度不一致
- 修复：初始 attributed string 也带 `NSFontAttributeName: font`（`NSDictionary* textAttrs=@{NSFontAttributeName:font};`）

### 文件变更
- `bridge/ime_bridge.m` — 签名加 `font_size`/`initial_text`；`font` 参数化；`typingAttributes` 设置；初始 attributed string 带 font；递归 `collectImeViews`；`ime_destroy_textview` 重写为整窗清扫；所有关键生命周期日志改 `fprintf(stderr)`
- `bridge/ime_bridge.dylib` — 重新编译至 `bridge/ime_bridge.dylib` + `/tmp/ime_bridge.dylib`
- `src/Widgets/Surface.php` — cdef 更新；create 传 `$fontSize` + `repositionImeOverlay()`；`detachImeTextview()` 重排；`draw()` 末尾逐帧 `repositionImeOverlay()`
- `src/Rendering/WidgetRenderer/SearchFieldRenderer.php` / `TextFieldRenderer.php` — 确认渲染字号 `min($height*0.5,14.0)`

### 验证
- `php85 -l src/Widgets/Surface.php` → No syntax errors
- `clang -dynamiclib -fobjc-arc -framework Foundation -framework AppKit -framework QuartzCore bridge/ime_bridge.m -o bridge/ime_bridge.dylib` → 编译成功；`cp` 到 `/tmp/ime_bridge.dylib`
- 用户截图确认：sf 输入 / 滚动 / 切 tf / 回 sf 全流程无重叠，字符高度一致，首焦可见
- 测试 harness：`UI2_DEBUG_MOUSE=1 php85 examples/surface-controls-demo.php 2> /tmp/scrollN.log`

### 当前未 commit 的修改
- `bridge/ime_bridge.m` / `bridge/ime_bridge.dylib` — 整窗清扫 + 字号参数化（最新）
- `src/Widgets/Surface.php` — IME 生命周期重排 + 逐帧重定位
- `src/Rendering/WidgetRenderer/SearchFieldRenderer.php` / `TextFieldRenderer.php` — （确认，无改动）

---

## Session: 2026-07-13 — ime_bridge 增加 Windows / Linux 支持

### 目标
让 IME 覆盖层（`Surface` 的 TextField / SearchField / TextArea）在三个平台都能用，沿用项目既有桥接结构（`.m` + `_win.c` + `_linux.c`）。

### 新增文件
- `bridge/ime_bridge_win.c` — Win32 实现：Unicode `EDIT` 控件作为 libui Area HWND 的子窗口
  - 单/多行：`vcenter!=0` → 单行；`vcenter==0` → `ES_MULTILINE`（TextArea）
  - IME：Win32 EDIT 原生处理组合输入；`WM_IME_START/ENDCOMPOSITION` 跟踪 `g_composing`
  - 文本变更：子类化 EDIT + 父类，父类 `WM_COMMAND/EN_CHANGE` 转发到 notify 回调（UTF-8 ↔ UTF-16 转换）
  - Tab：EDIT 子类吞掉 `VK_TAB` 调 tab 回调（Shift 判定用 `GetKeyState`）
  - 字体：`CreateFontW` 按 `font_size` 现实 DPI 计算；句柄存 `g_font`，destroy 时释放
  - 焦点：`SetFocus` / 清焦点回 parent
  - 符号用 `__declspec(dllexport)`，与 PHP cdef 同名
- `bridge/ime_bridge_linux.c` — GTK3 实现：无边框 `GtkWindow`（popup 类型）覆盖在字段屏幕矩形上
  - 单/多行：`GtkEntry` / `GtkTextView`（buffer `changed` 信号）
  - IME：GTK IM context 原生；`g_composing` 暂为 best-effort（FALSE）
  - 文本变更：`changed` 信号 → `fire_notify`（UTF-8 直接匹配）
  - Tab：`key-press-event` 吞 `GDK_KEY_Tab` / `ISO_Left_Tab`
  - 坐标：Area 相对 → 屏幕坐标（`gdk_window_get_origin` + 分配偏移）
  - 字体：CSS provider（避免 `gtk_widget_override_font` 弃用警告）
  - 焦点：`gtk_widget_grab_focus`

### 修改文件
- `src/Widgets/Surface.php`
  - 文档块改为跨平台说明（NSTextView / EDIT / GTK entry 三者）
  - 加载逻辑：`imeBridgePath()` 按 `PHP_OS_FAMILY` 选 `ime_bridge.dylib`（含 `/tmp` 回退）/ `.so` / `.dll`；cdef 保持**完全不变**（三平台符号一致）
  - 新增 `private static function imeBridgePath(): ?string`
- `bridge/README.md` — 新增「IME Bridge」段：三平台 build 命令 + 统一 API 表

### 验证
- `php85 -l src/Widgets/Surface.php` → No syntax errors
- Linux 桥：`gcc -shared -fPIC $(pkg-config --cflags --libs gtk+-3.0) bridge/ime_bridge_linux.c -o /tmp/ime_bridge_linux_test.so` 编译 + 链接成功，`nm` 确认导出 `ime_create_textview` / `ime_destroy_textview` / `ime_set_notify_callback` / `ime_set_view_frame` 等符号
- Windows 桥：macOS 无 `windows.h` 无法编译；逐行审查 + 花括号配平检查通过，模式与既有 `webview_bridge_win.c` / `context_menu_win.c` 一致
- 待目标平台编译验证：Windows（MSVC/MinGW）、Linux（GTK3 dev 安装后 `gcc -shared`）

### 未 commit 的修改
- `bridge/ime_bridge_win.c`（新增，未编译）
- `bridge/ime_bridge_linux.c`（新增，已编译验证）
- `src/Widgets/Surface.php`（平台加载）
- `bridge/README.md`（文档）

---

## Session: 2026-07-13 — ime_bridge 接入项目构建系统

### 目标
把 IME 桥接编译纳入 `composer` 构建流程，与既有 `build:bridge`（WebView 桥）一致，让用户一行命令即可在三平台编译 `ime_bridge`。

### 改动（`composer.json`）
- 新增 `build:ime` 脚本：沿用 `build:bridge` 的 `PHP_OS_FAMILY` 分派 + `@php -r passthru(...)` 模式
  - macOS：`clang -dynamiclib -fobjc-arc -framework Foundation -framework AppKit -framework QuartzCore bridge/ime_bridge.m -o bridge/ime_bridge.dylib`
  - Linux：`gcc -shared -fPIC bridge/ime_bridge_linux.c -o bridge/ime_bridge.so $(pkg-config --cflags --libs gtk+-3.0)`（`$(...)` 用 `\` 转义，防外层 shell 提前展开）
  - Windows：`gcc -shared bridge/ime_bridge_win.c -o bridge/ime_bridge.dll -luser32 -lgdi32`
- 聚合 `build` 脚本追加 `@composer build:ime`（现顺序：`build:pebview` → `build:bridge` → `build:ime`）

### 验证
- `php -l composer.json` → JSON OK；`build:ime` 存在于 scripts；`build` 聚合含三项
- 本机 `composer build:ime` 成功：生成 `bridge/ime_bridge.dylib`（72KB，13:32 时间戳）
- `nm -gU bridge/ime_bridge.dylib` → 确认导出 `_ime_create_textview` / `_ime_destroy_textview` / `_ime_set_notify_callback`（Mach-O 用 `-gU` 而非 `-D`）
- Windows/Linux 分支需在对应目标平台实编译验证（命令结构与 `build:bridge` 一致）

### 未 commit 的修改
- `composer.json`（新增 `build:ime` + 聚合 `build`）

---

## Session: 2026-07-13 — 「全面转向自绘」简化可行性审计

### 用户意图
参考自绘架构分析，要求"先做精简"：去除原生封装（`src/Fields/*`、`TextAreaControl`/`SearchFieldControl`/`ComboboxControl`/`ToggleSwitch`、上游 `Generated\*` 控件类）。

### 审计结论（不能直接删）
1. **IME 耦合（最危险）**：`Surface.php:1094` IME 路径依赖 `TextAreaControl` 持有 spec；`TextAreaSpec.$control` 反向引用原生控件。覆盖层浮动于原生控件之上 —— 删原生控件即删刚完成的 IME 功能。
2. **示例/测试引用**：4 测试（`FieldsTest`/`WidgetsTest`/`InputControlsTest`/`EditControlsTest`）+ 多示例（`test-fields`、`test-widgets`、`all-components`、`surface-controls-demo`、`test-circle-progress`、`renderer-button-demo`）引用原生封装。
3. **Spec 覆盖缺口**：自绘 Spec 有 TextField/TextArea/SearchField/Checkbox/Switch/Select/RadioGroup/Slider/Progress… 但缺 **DatePicker / FilePicker / Password / Number** 四个 Field 的自绘等价物。

### 安全分阶段路径（见 task_plan Phase L–P）
- M：补 4 个缺失 Spec
- N：IME 覆盖层解耦（浮动于绘制矩形）或保留原生文本控件
- O：迁移示例/测试到 Spec
- P：最后才删除原生封装

### 当前状态
- 仅完成审计（Phase L ✅）；未做任何删除
- 已写入 `findings.md`（简化可行性审计）+ `task_plan.md`（Phase M–P）+ 本日志
- 待用户确认：是否进入 M–P 分阶段，或仅维持混合架构

### 未 commit 的修改
- `findings.md`（新增审计小节）
- `task_plan.md`（新增 Phase L–P）
- `progress.md`（本日志）

---

## Session: 2026-07-13（续）— Phase M：补自绘 Spec 缺口（首批 Number/Password）

### 决策
用户选"分步纯自绘迁移 (M→P)"。Phase M 目标是补齐 `src/Fields/` 中缺失自绘 Spec 的 Field，使原生封装可被替代。

### 本批完成（纯增量，零破坏）
- 新增 `src/Rendering/WidgetRenderer/NumberSpec.php` — `type()='number_field'`，含 `min/max/step` 提示字段
- 新增 `NumberRenderer.php` — 几何完全镜像 `TextFieldRenderer`，数值过滤由 control 负责
- 新增 `PasswordSpec.php` — `type()='password_field'`，含 `reveal` 属性（peek 切换）
- 新增 `PasswordRenderer.php` — 值以 `•` 掩码（UTF-8 安全 `mb_strlen`），`reveal=true` 时显原值
- `RendererRegistry::default()` 注册 `number_field` / `password_field`

### 验证
- 5 文件 `php85 -l` 全部 No syntax errors
- `read_lints` 对 WidgetRenderer 目录 0 诊断
- headless 加载 `RendererRegistry::default()`：`number_field`/`password_field`/`text_field` 均 registered（无需 FFI）

### 待决（Phase M 余下）
- `DatePickerField` / `FilePickerField`：本质是 OS 对话框（modal Picker / 系统文件框），非可绘制的 Area 内控件。**建议保留原生封装**，或在自绘世界表示为"点击触发 Picker 的按钮"（需设计决策）。这影响 Phase P 能否完全删 `src/Fields/`。

### 下一步
- 确认 DatePicker/FilePicker 处置 → 完成后进入 Phase N（解耦 IME 覆盖层，从依赖 `TextAreaControl` 改为浮于绘制矩形）

### 未 commit 的修改
- `src/Rendering/WidgetRenderer/NumberSpec.php`（新增）
- `src/Rendering/WidgetRenderer/NumberRenderer.php`（新增）
- `src/Rendering/WidgetRenderer/PasswordSpec.php`（新增）
- `src/Rendering/WidgetRenderer/PasswordRenderer.php`（新增）
- `src/Rendering/WidgetRenderer/RendererRegistry.php`（注册两项）

---

## Session: 2026-07-13（续2）— Phase M 收尾：DatePicker/FilePicker 自绘 Spec + FilePickerDialog

### 决策回放
用户选「自绘为点击触发 Picker 的按钮」——在 Surface 里画按钮，点击调起 `src/Pickers/` 模态框。勘察发现：`src/Pickers/` 仅有 Color/Date/Font/Time 四框，**无 FilePicker**；原生 `FilePickerField` 直接走 `\Libui\Dialogs($parent)->openFile()`。

### 本批完成（纯增量，零破坏）
- `DatePickerSpec.php`（type=`date_picker`）+ `DatePickerRenderer.php`：只读字段样式（surface 填充 + primary/track 边框），右侧 chevron 用两条 `StrokeLine`；按下叠加 `color.selection`
- `FilePickerSpec.php`（type=`file_picker`）+ `FilePickerRenderer.php`：同上，文本显示路径/占位
- `src/Pickers/FilePickerDialog.php`：**新增**，封装 `\Libui\Dialogs($parent)->openFile()`，与 `DatePickerDialog` 等对称，API 为 `FilePickerDialog::pick(Window $parent): ?string`
- `RendererRegistry::default()` 注册 `date_picker` / `file_picker`

### 点击→Picker 接线约定
Spec 是不可变值对象，无回调；点击由 Surface 层负责：`$surface->onClick('nodeId', fn () => DatePickerDialog::pick($window))` / `FilePickerDialog::pick($window)`。`DatePickerSpec`/`FilePickerSpec` 文档注释已写明该约定。

### 验证
- 6 文件 `php85 -l` 全部 No syntax errors（含 `RendererRegistry.php`）
- `read_lints` 对 WidgetRenderer 目录 0 诊断
- headless 加载 `RendererRegistry::default()`：`date_picker`/`file_picker`/`text_field`/`number_field`/`password_field` 均 registered（无需 FFI）

### Phase M 状态
✅ 四组缺失 Spec（Number/Password/Date/File）全部补齐，原生 `Fields/*` 的可自绘替代面已覆盖。下一步进入 **Phase N（解耦 IME 覆盖层）**——把 `Surface` 的 IME 路由从依赖原生 `TextAreaControl` 改为浮于绘制矩形（最风险一步，会动到刚完成的 IME 功能）。

### 未 commit 的修改
- `src/Rendering/WidgetRenderer/DatePickerSpec.php`（新增）
- `src/Rendering/WidgetRenderer/DatePickerRenderer.php`（新增）
- `src/Rendering/WidgetRenderer/FilePickerSpec.php`（新增）
- `src/Rendering/WidgetRenderer/FilePickerRenderer.php`（新增）
- `src/Pickers/FilePickerDialog.php`（新增）
- `src/Rendering/WidgetRenderer/RendererRegistry.php`（注册两项）

---

## Session: 2026-07-13（续3）— IME 性能优化（Phase N 优化分支）

### 用户反馈
"隐藏原生组件功能能用了 但是 很卡 聚焦等几秒 输入后 响应也慢，迟早要换或优化的"。即 IME 当前的痛点纯是**性能**，不是功能。

### 根因（读 `src/Widgets/Surface.php` IME 实现）
1. **每键 ~5 次 `fwrite(STDERR)` + `fflush(STDERR)`**（`notifyFn` 内，原 544–565 行）——强制 syscall 刷新 stderr，是"输入后响应慢"头号元凶。
2. **每帧 `withState` 写 stderr**（原 1114 行，只要画到 `TextAreaSpec` 就 ~60fps 写）——是整窗"很卡"主因。
3. **每次聚焦重新 `\FFI::cdef()` 解析整段 C 头**（原 491 行）——"聚焦慢"的来源之一。

### 改动（`src/Widgets/Surface.php`）
- 新增 `imeDbg(string $msg)` 私有方法：仅当 `getenv('UI2_DEBUG_IME')==='1'` 才写 stderr（与既有 `UI2_DEBUG_MOUSE` 同模式，默认关闭）。
- IME 热路径 18 处 `fwrite(STDERR)`/`fflush(STDERR)` 全部改为 `$this->imeDbg(...)`（闭包内用 `$surface->imeDbg(...)`，`withState` 内用 `$this->surface->imeDbg(...)`）。
- 新增缓存属性 `$imeBridgeCdef`；`handleImeFocus` 改为仅在首次解析 bridge cdef，之后复用，不再每次聚焦重解析。
- 保留 `detachImeTextview` 中 `$this->imeBridgeFfi = null`（现有守卫逻辑不变），仅 cdef 句柄跨 detach/reattach 复用。

### 验证
- `php85 -l src/Widgets/Surface.php` → No syntax errors
- `read_lints` → 0 诊断
- grep 确认 IME 路径（`handleImeFocus`/`IME`/`Warning`/`withState: TextAreaSpec`）已无残留 `fwrite(STDERR)`/`fflush(STDERR)`；仅剩 `FocusManager onChange`（单次聚焦，非热路径）与已门控的 `dbgMouse`

### 未做（决策说明）
**未做"去掉原生 NSTextView、纯自绘 rect"的深层重写**：libui 的 `AreaKeyEvent->Key` 是单字节 ASCII，无法输入中文/emoji；当前中文 IME 正依赖原生 `NSTextView` 覆盖层。去掉它即丧失中文输入能力，且风险高。本次以"优化"分支直接解决卡顿，符合用户"迟早要换或优化的"中"优化"那条。是否进一步"换"（去原生控件）待用户定夺。

### 未 commit 的修改
- `src/Widgets/Surface.php`（IME 日志门控 + cdef 缓存 + `imeDbg` 方法）

---

## Session: 2026-07-13（续4）— Phase O 收尾：FieldsTest 迁移到自绘 Spec

### 决策
用户选"收尾 FieldsTest"（Phase O 最后一项）。原生 `Fields\*` 因 IME 阻塞无法在 Phase P 删除，故**不删原生类、只迁移测试**，使 `FieldsTest` 脱离原生 API、转为验证自绘 Spec 值对象。

### 改动（`tests/FieldsTest.php`，整文件重写）
- 移除全部 `Libui\*` / `Yangweijie\Ui2\Fields\*` 原生导入；改测 `Rendering\WidgetRenderer\*Spec` 值对象。
- 每个原生字段 → 对应自绘 Spec 映射断言：
  - TextField→`TextFieldSpec`、PasswordField→`PasswordSpec`、SearchField→`SearchFieldSpec`、NumberField→`NumberSpec`、CheckboxField→`CheckboxSpec`、RadioGroup→`RadioSpec`、ComboBox/EditableComboBox→`SelectSpec`、DatePickerField→`DatePickerSpec`、TextAreaField→`TextAreaSpec`、ProgressBarField→`ProgressSpec`、SliderField→`SliderSpec`、FilePickerField→`FilePickerSpec`、SeparatorLine→`separator` renderer。
- 核心断言：每个 Spec `type()` 在 `RendererRegistry::default()` 已注册（即"原生字段有自绘 renderer 接管"，为 Phase P 删原生封装提供依据）。
- 覆盖不可变性：更新值 = 构造新 Spec，原 Spec 不变。

### 验证
- `php85 vendor/bin/pest tests/FieldsTest.php` → **20 passed**（headless，无需 FFI）
- `task_plan.md` Phase O 标记 ✅ complete

### 未 commit 的修改
- `tests/FieldsTest.php`（整文件重写：原生 `Fields\*` 测试 → 自绘 Spec 值对象测试）
- `task_plan.md`（Phase O 状态 in_progress → ✅ complete）

---

## 2026-07-14 — Tetris 示例完善（自绘布局）

### T1–T3: 游戏逻辑 + 侧边栏初版
- `examples/tetris.php` 从旧版重写为混合架构：Area+AreaDelegate（游戏）+ Surface+LayoutNode+LabelSpec（侧边栏）
- 游戏逻辑完整：7 种方块、旋转踢墙、消行计分、等级递增、幽灵方块、硬降、暂停

### T4: Surface 侧边栏方案验证（失败）
- **问题**：Surface `root()` 返回非滚动 Area，在 libui Box 中拿不到固定宽度，侧边栏被挤出窗口
- **尝试**：自定义 `PreviewSpec` + `PreviewRenderer`（用 `FillRoundedRect` 在 Surface 内画预览方块）→ 解决了 preview 但侧边栏宽度仍不可控
- **结论**：游戏类布局不适合 Surface——Surface 的 Area 在 Box 中没有"自然尺寸"概念

### T5: LabelSpec text 可变
- `src/Rendering/WidgetRenderer/LabelSpec.php` — `text` 属性从 `readonly` 改为 mutable
- 允许 `$spec->text = "Score: 42"` 运行时更新 + `Surface::redraw()` 触发重绘

### T6: 单 Area 全自绘方案（最终方案）
- 全部画进一个 Area+AreaDelegate：左侧游戏区域 + 右侧侧边栏
- `drawString()` 直接绘制 TETRIS 标题、Score、Level、Lines、NEXT 标签
- `fillRect()` 绘制侧边栏背景、预览方块
- 消除所有 Surface/Area 竞争问题

### T7: 布局修正
- 游戏区域垂直居中：`$boardY = max(0, (areaH - BOARD_H) / 2)`
- 所有绘制函数（`drawCell`/`drawBoard`/`drawGhost`/`drawLockedCells`/`drawOverlay`）接受 `$boardY` 偏移参数
- GAME OVER/PAUSED 覆盖层用 `extents()` 手动测量居中（`DrawTextAlign::Center` 在 macOS 不可靠）

### T8: 动态标签
- draw 回调中直接拼接 `$this->state->score` 等变量
- 每次 `queueRedrawAll()` 重绘时标签自动更新，无需额外机制

### 修复的错误
| 错误 | 原因 | 修复 |
|------|------|------|
| `Unknown named parameter $grow` | `LayoutNode::leaf()` 不接受 `grow` 参数 | 移除该参数 |
| `Can't use nullsafe operator in write context` | PHP 不允许 `?->` 赋值 | 改为 `if ($x !== null) $x->prop = ...` |
| `Call to private Color::__construct()` | Color 构造函数是 private | 改为 `Color::rgb()` / `Color::rgba()` |
| 侧边栏超出窗口 | Surface Area 在 Box 中无固定宽度 | 放弃 Surface，改单 Area 全自绘 |
| GAME OVER 文字偏左 | `DrawTextAlign::Center` macOS 不可靠 | 用 `extents()` 手动测量居中 |

### 关键架构决策
- **单 Area 全自绘**是游戏类布局的最佳方案
- **Surface 适合表单 UI**，不适合需要固定宽度侧边栏的游戏布局
- **未来方向**：为 Surface 增加 `CanvasSpec`，支持在 LayoutNode 树中嵌入自定义绘制回调
