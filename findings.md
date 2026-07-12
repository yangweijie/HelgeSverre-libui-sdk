# 发现 / 技术笔记 (Findings)

## libui PHP SDK 关键陷阱
- **命名空间**：`Libui\Color`（非 `Libui\Draw\Color`）；`Libui\Draw\Brush`；`Libui\Text\FontDescriptor`（非 `Libui\Draw\FontDescriptor`）；`Libui\Draw\{DrawContext,StrokeParams}`
- **主循环模型**：`App::new()->window()->run()` 实际调用 `Ffi::main()`，**不是** `Loop::run()`。因此 `Loop::$running` 始终 false，`Loop::isRunning()` 在 GUI 运行时必然返回 false。→ 不要依赖它做 guard。
- **定时器排程**：在按钮回调 / 事件回调里，`Loop::delay`（`uiTimer`）不可靠；应优先 `Loop::defer`（`uiQueueMain`，保证下一主循环 tick 执行），并配 `$done` 标志防重复触发。
- **文本绘制对齐 bug**：`DrawContext::drawString($text, $font, $color, $x, $y, $width, $align)` 的 `DrawTextAlign::Center` 在已调用 `fillRect` 全屏遮罩的同一绘制上下文里**不生效**（文字贴左）。绕过方法：把 `x` 作为**锚点**而非左边界 —— 用 `x = W*0.25, width = W*0.5` + Center 实现区域精确居中。

## UTF-8 文本陷阱（关键！）
- libui 的 `drawString` 对**非法 UTF-8 字节序列**不会报错，而是渲染成乱码（方块 / 花色符号 ♣♣ 等）。
- PHP `substr()` 按**字节**截断。CJK 字符 UTF-8 占 3 字节，emoji 占 4 字节。若截断点落在字符中间 → 非法序列 → 乱码。
- **修复**：所有对显示文本的长度截断一律改用 `\mb_substr($s, 0, $n, 'UTF-8')`，按字符截断。
- 同样适用于卡牌描述 `Combo::describe()` 内部的子串处理（见 `GameController.php` line 1352）。

## 跨平台字体
- macOS：`.AppleSystemUIFont`（含中文 + ♥♦♣♠）
- Windows：`Microsoft YaHei`（雅黑，含花色符号）
- Linux：`Noto Sans CJK SC`（缺失时退回 Pango 通用 `Sans`）
- 用 `uiFontFamily()` 运行时按 `PHP_OS` 解析 + `UI_FONT` 环境变量覆盖。`if (!defined('FONT')) define(...)` 防重复定义（测试多次 include）。

## 斗地主逻辑注意点
- `Game::advanceTurn()`：连续 2 人 pass → `lastPlay = null`（新一轮首出）。GUI 需保存上一手快照 `$lastShownPlay` 回退显示。
- 武将技能（skill / arm / counter / skip / frozenSkip）通过 `Game::emit()` 事件广播，`GameController::onGameEvent` 转中文日志 + 触发音效。

## 测试
- Pest 框架；`tests/OnePieceDoudizhuTest.php`，**17 项全过**（含 z-order 命中测试、托管自动出牌测试）。
- 运行（在 `/Volumes/data/git/php/HelgeSverre-libui-sdk` 目录下）：`php85 vendor/bin/pest tests/OnePieceDoudizhuTest.php --no-coverage`
- `Sound` 类无头环境安全（禁用即静音），便于测试。

---

## 新子系统：渲染引擎 (`src/Rendering/`)

### RenderCommand 模式
- `RenderCommand` 是一个可序列化的绘制指令结构体（BoxShadow→shadowRect→fill→clip→translate→drawRect→drawRoundedRect→drawCircle→drawString），携带颜色/尺寸/字体等参数
- `CommandExecutor` 消费 RenderCommand 列表，在 `DrawContext` 上执行实际绘制。批量命令一次性执行，减少 FFI 往返
- `RenderCommandList` 管理有序命令列表，支持隐式新建模式（`begin()` → `addShadow()` / `addFill()` / `end()`）。每次 `begin()` 返回 `$this` 后续入栈
- `CircleProgressDelegate` 从 Widgets 命名空间移到 Rendering，测试覆盖弧线/文本绘制

### DesignTokens 不可变主题系统
- `DesignTokens` 是一个不可变值对象（`$tokens->with[key=>val]()` 返回新实例）。包含基础色板 + 组件专属 token + 派生色计算（`shade()`/`tint()`/`alpha()`/`isLight()`/`luminance()`）
- `ThemeKey` 枚举定义了 token 键名（`PRIMARY`/`BG`/`TEXT`/`BORDER_RADIUS`/`FONT_SIZE` 等），按组件分组（`SURFACE_*`/`CHART_*`/`CIRCLE_PROGRESS_*`/`TOGGLE_*` 等）
- 配套 `WidgetStyle` trait（提供 `resolveColor($key, $overrides)` / `resolveStyle($key, $overrides)`），Widget 类添加 `->withTokens(DesignTokens)` 即可驱动渲染
- 扩展类型：`hoverColor`/`disabledColor` wash、`focusRing`（outer glow）、`hairline`（1px border）、`DARK` 主题预设

### WidgetRenderer 注册表
- `WidgetRenderer` 接口（`render(CommandList, $bounds, $state): void`）+ `RendererFactory::register($type, $renderer)` 静态注册表
- 内置实现：ButtonRenderer, CardRenderer, CheckboxRenderer, RadioRenderer, SliderRenderer, ProgressRenderer, TextFieldRenderer, SelectRenderer
- `RendererButton` — 扩展 Composite，底层是 Button，但外观完全由 WidgetRenderer 渲染。跨 custom-drawn 与 libui 原生互操作的桥梁
- `RendererFactory::types()` 返回已注册类型列表，用于自动检测/枚举

## 新子系统：布局引擎 (`src/Layout/`)

### Flexbox 布局
- `LayoutStyle` — 不可变样式对象（width/height/min/max/padding/margin/flexGrow/flexShrink/alignSelf），`with()` 返回新实例
- `LayoutNode` — 节点树（children/parent），递归计算。`layout()` 传入位置约束，`measure()` 返回子树尺寸。缓存结构 + 脏标记
- `FlexLayout::layout($nodes, $constraints)` — 经典 flexbox 算法：尺寸计算 → 主轴排列（flexGrow 均分剩余）→ 交叉轴对齐（stretch/center/flex-start/flex-end）。`flex-wrap` 显式 `<br>` 节点实现换行
- 16 项测试覆盖：尺寸、边距、flexGrow、换行、嵌套、交叉轴、min/max、溢出

### Grid 布局
- `GridTrack` — 行列轨道定义（固定 px / fr 弹性 / min-content / max-content）。`resolveTrack()` 计算 FR 分配
- `GridStyle` — 不可变样式（columns/rows/gap/placement），`with()` 返回新实例
- `GridLayout::layout($nodes, $gridStyle, $constraints)` — 轨道计算 → 单元分配 → 间距排列。7 项测试

## 新子系统：Surface 画布控件 (`src/Widgets/Surface.php`)

### 架构
- `Surface` 是一个 1056 行的 `Composite`，内部包裹一个 `Area` + `AreaDelegate`。用单个 libui Area 实现完整控件渲染
- 可放置在任何 libui 容器（Box/Form/Grid/Tab）中，不受 WebView 子窗口限制
- 内部组合：
  - `FlexLayout` — 布局引擎驱动子控件位置
  - `RendererFactory` — WidgetRenderer 注册表驱动绘制
  - Commands 模式 — 多帧缓存防重绘闪烁

### 鼠标路由
- `mouse()` 事件 → `PointerEvent` 统一封装（`x/y/down/held/up/modifiers/clickCount/timestamp`）→ 分发给焦点控件
- hitTest 遍历 Renderer 边界框确定目标

### 事件系统 (`src/Events/`)
- `PointerEvent` — 统一鼠标事件模型（封装 libui AreaMouseEvent）
- `KeyboardEvent` — 统一键盘事件模型（封装 libui AreaKeyEvent）
- `FocusManager` — 焦点管理（`requestFocus()` / `blur()` / `getFocused()` / `focusNext()` tab 导航）
- 通过 `Surface` 的事件分发层注入给子控件

### 语义 / 无障碍 (`src/Semantics/`)
- `WidgetRole` 枚举（`Button`/`Checkbox`/`Radio`/`Slider`/`TextField`/`Label`/`Image`/`List`/`TreeGrid`/`Tab`/`ProgressBar`/`StatusBar`）
- `SemanticsNode` — 角色 + 标签 + 值 + 状态（enabled/disabled/checked/pressed/focused/selected） + 边界框 + 子节点。构建树后提供 Flat 视图查询

### 控件清单（未 commit）
18 个 Renderer-based 控件：BreadcrumbControl, ComboboxControl, DialogControl, DrawerControl, DropdownMenuControl, ListControl, PaginationControl, PopoverControl, RendererButton, ScrollViewControl, SearchFieldControl, SheetControl, TabControl, TableControl, TextAreaControl（其中 RendererButton 是 Bridge；其余为 Surface 自绘）

### 核心发现
- Area 模式的极限：Surface 用 1056 行就封装了完整的布局+渲染+事件路由+语义栈，说明 libui 的 Area API 对自绘 UI 足够通用
- FlexLayout + RendererRegistry + Surface 组合提供了类似 Flutter 的（布局 → 渲染 → 事件）三阶段流水线
- 但性能瓶颈在单线程 FFI 调用：每帧所有子控件统一合并在一个 DrawContext 内批量绘制，没有独立的脏区域追踪

## DesignTokens 核心发现
- **FontDescriptor** 来自 `Libui\Text\FontDescriptor`（不是 `Libui\Draw`）— `new FontDescriptor(string $family, float $size, int $weight = 400, int $italic = 0)`
- **font($size) 工厂**：返回新 `FontDescriptor` 实例（不可缓存），render 时一次性创建用完丢弃，无泄漏风险
- **Arial 规模**：15/17 个 renderer 硬编码 `'Arial'` — 通过 sed + 手动修复完成迁移
- **字号收敛**：button=16, body=14, caption=12, heading=24, subtitle=18, label=14, input=14, table=13

## bin/ui2 CLI 要点
- **架构**：单文件入口手写路由（避免 symfony/console 依赖），$argv[1] 匹配
- **check 命令**：验证 PHP ≥8.5、ext-ffi/phar/mbstring、vendor、native libs、bridge、micro.sfx
- **init 命令**：`composer create-project yangweijie/ui2-skeleton $name` 脚手架（需 skeleton 包存在）

## 快照测试要点
- 轻量文件系统 JSON 快照：`Snapshot::assert($name, $data)` — 首次写基线，后续对比
- 基线文件在 `tests/__snapshots__/`，DesignTokens 用 ReflectionClass 读私有 `$tokens`

## Capability 守卫系统要点
- **接口**：`Capability`：`name()` / `available()` / `reason()` / `dependencies()`
- **注册表**：`CapabilityRegistry` 单例，首次 getInstance() 自动通过 autoload 发现内置能力
- **内置 5 个**：audio（bridge/audio.dylib）、tray（PebView）、hotkey（bridge/hotkey.dylib）、system-info（utopia-php/system）、process（illuminate/process）
- **测试**：23 个 — 接口约定验证、注册表自动注册、require 异常、自定义能力注册

---

## 2026-07-12 — TextArea IME 输入修复

### 问题
在 Surface 的 TextAreaControl 中输入中文/数字，IME 候选词选择后文本不显示（回显失败）。

### 根因（三重 bug）

**Bug 1: `withState()` 丢失 control 回引用**
- `Surface::withState()` 每帧创建新的 TextAreaSpec，但未传递 `control: $spec->control`
- 结果：TextAreaSpec 的 `$control` 永远为 null，TextAreaControl 的 `$value` 与 spec 不同步

**Bug 2: `withState()` 读取 stale spec 值**
- `withState()` 读取 `$spec->value` 而非 TextAreaControl 的 `$this->value`
- TextAreaControl 的 `syncSpec()` 更新 `$this->leaf->spec`，但 `withState()` 读的是另一份 spec 实例

**Bug 3: IME 回调 segfault**
- `ime_get_text_view()` 返回 NULL 指针（空 FFI CData 对象，非 `null`）
- `$text_view_ptr !== null` 永远为 true → `ime_is_composing($text_view_ptr)` 传入 NULL → **segfault** → 回调静默死亡
- 修复：`(int) $text_view_ptr === 0` 检查实际指针值

### 修复内容

| 文件 | 修改 |
|------|------|
| `src/Rendering/WidgetRenderer/TextAreaSpec.php` | 添加 `$control` 回引用属性（已存在） |
| `src/Widgets/TextAreaControl.php` | 添加 `getCursor(): int` 方法；`syncSpec()` 设置 `control: $this` |
| `src/Widgets/Surface.php` | `withState()` 读取 `$control->getValue()` + `$control->getCursor()`；保留 `control: $control`；NULL 指针检查 `(int) $text_view_ptr === 0` |

### 技术发现
- **PHP FFI NULL 指针陷阱**：FFI 返回的 NULL 指针是 CData 对象，永远不 === null。必须用 `(int) $ptr === 0` 检查实际值。
- **Surface 渲染循环**：每帧 `withState()` → 新 spec 实例 → 渲染 → TextAreaRenderer 读 `$spec->value`。如果 spec 与 Control 不同步，渲染永远看到旧值。
- **IME 回调 segfault 特征**：日志中断、无错误信息、下一个 keystroke 的日志立即出现（前一个回调已死）。

### 验证
- `php -l` 语法检查通过
- 待运行 `php85 examples/surface-controls-demo.php` 手动验证中文输入回显

### imagecolorat() 返回格式
- GD `imagecolorat()` 返回 `0xAARRGGBB`（**不是** `0xAABBGGRR`）
- Bits 0-7: Blue
- Bits 8-15: Green
- Bits 16-23: Red
- Bits 24-31: Alpha（0=不透明，127=透明）

### Alpha 反转
- GD alpha: 0=不透明，127=透明
- 渲染器 alpha: 0.0=透明，1.0=不透明
- 转换公式：`1.0 - (($rgba >> 24) & 0x7F) / 127.0`

### imagealphablending()
- GD 默认 `imagealphablending($im, true)` — 半透明像素与黑色背景混合
- 像素提取前必须调用 `imagealphablending($im, false)` 获取原始 alpha 值

### ImageControl / AvatarControl 架构
- `ImageControl::fromPng()` 和 `fromFile()` 都委托给内部 GD 加载逻辑
- `AvatarControl::fromPng()` 和 `fromFile()` 都委托给 `fromGdImage()`
- `ImageSpec` 接受 flat `float[]` RGBA 数组、`imgW`、`imgH`
- 渲染管线：`sampleNearest()`/`sampleLinear()` → `drawSampledPixels()` → RLE 合并 → `fillRect()`

### SVG 支持
- GD 不支持 SVG 解析
- 项目有 `SvgView` 控件（`src/Widgets/SvgView.php`）专门用于 SVG 矢量渲染
- 使用 SvgDelegate 解析路径并通过 DrawContext 绘制

---

## Elm Architecture 状态管理 (`src/State/`)

### 架构映射
- **Model** → `readonly` class（PHP 8.4+），实现 `Model` 空标记接口
- **Msg** → PHP `UnitEnum`（不一定带数据），实现 `Msg` 空标记接口
- **Update(Model, Msg) → (Model, Effect[])** → 纯函数返回 `UpdateResult`
- **Effect** → 抽象基类，描述副作用（目前为标记，后续扩展）
- **AppRuntime** → 持有 Surface + Model + Update fn + View fn；dispatch(Msg) → Update → 新 Model → 自动 redraw

### 关键设计
- 更新函数是纯函数——不访问 IO 或全局状态
- `AppRuntime::dispatch()` 返回新 Model 实例（不可变）
- 演示集成：Surface 的 `onClick(id, fn)` 路由 → dispatch Msg → 更新 LayoutNode spec → `$surface->redraw()`
- Counter 示例：CounterMsg::{Inc,Dec,Reset} → counterUpdate → 更新 LabelSpec 文本

### 限制
- 没有内置 Effect 运行时（未来需要 EffectRunner 调度异步副作用）
- 没有中间件/DevTools（Elm Debugger 那样的事件日志）
- 当前最适合单一 Surface 应用；多 Surface 需要共享状态方案

---

## DSL：NativeLoader (`.native` → LayoutNode)

### 架构
- `NativeLoader::load(string $path): LayoutNode` — 读取 .native XML → SimpleXML 解析 → LayoutNode 树
- 元素标签映射：`Row`→`LayoutNode::row()`, `Column`→`LayoutNode::column()`, 其他→`LayoutNode::leaf(id, spec)`
- 标签名（Button/Label/Checkbox/Slider/Progress/Radio/Select/Panel/Card/ScrollView/TextField/SearchField/Dialog/Drawer/Sheet/Popover/Breadcrumb/DropdownMenu/List）→ 对应 `*Spec` 类
- `ScrollView` 特殊处理：生成 viewport row（ScrollViewSpec） + content column
- XML 属性 → ReflectionClass 构造函数参数映射，自动类型转换（numeric→float, "true"/"false"→bool）

### Counter.native 示例
```xml
<Card bordered="false" radius="12" elevation="low">
  <Column gap="8">
    <Label id="counter-dsl-label" text="0" size="28" align="center" />
    <Row gap="8" justify="center">
      <Button id="counter-dsl-dec" label="−" variant="secondary" radius="6" />
      <Button id="counter-dsl-inc" label="+" radius="6" />
    </Row>
    <Row gap="8" justify="center">
      <Button id="counter-dsl-reset" label="Reset" variant="secondary" radius="6" />
    </Row>
  </Column>
</Card>
```

### 限制
- 不支持 ImageSpec（像素数据是运行时动态的，不适合 XML）
- 没有条件/循环/表达式（纯声明式）
- ScrollView 嵌套需要手动在 XML 里安排 Row > Column 结构
