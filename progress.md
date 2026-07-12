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
