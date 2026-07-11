# 架构

## Composite 模式

核心抽象是 `Composite`——一个由多个控件组合而成的抽象基类。`Composite` 将多个子控件封装在 `root()` 方法之后，整个组合可以像单个控件一样添加到容器（`Box`、`Form`、`Grid`）中。

```php
abstract class Composite implements HasValue
{
    abstract public function root(): Control;
    public function value(): mixed { /* 在子类中覆盖 */ }
    public function setValue(mixed $value): static { /* 覆盖 */ }
}
```

所有容器补丁（`Box`、`Form`、`Grid`、`Group`、`Tab`）都能透明地接受 `Composite` 子元素——它们在内部调用 `$composite->root()`。

## EmitsEvents Trait

一个轻量级事件发射器 trait。将其加入任何类即可使用 `on(event, handler)` / `emit(event, data)`。

```php
class MyWidget extends Composite
{
    use EmitsEvents;

    public function doSomething(): void
    {
        $this->emit('change', $this->value());
    }
}

$widget->on('change', fn ($val) => print("Changed: {$val}"));
```

所有 Field 组合控件都使用此 trait，在输入值变化时发射 `'change'` 事件。

## 渲染引擎 (`src/Rendering/`)

三个子系统协同工作，提供结构化的绘制管线。

### RenderCommand 管线

`RenderCommand` 是一个可序列化的绘制指令——不直接调用 `DrawContext` 方法，而是构建命令列表并批量执行：

```php
use Yangweijie\Ui2\Rendering\RenderCommandList;

$cmd = new RenderCommandList();
$cmd->begin()
    ->addBoxShadow(offsetX: 2, offsetY: 2, blurRadius: 8, color: [0, 0, 0, 0.2])
    ->addFill(0x3B82F6)
    ->addRoundedRect(10, 10, 100, 50, 8)
    ->addTranslate(10, 10)
    ->addDrawString('Hello', 0, 0, $font, 0xFFFFFF);
// $cmd->execute($drawContext) — 顺序执行所有命令
```

`CommandExecutor` 在 `DrawContext` 上消费命令列表。`RenderCommandList` 管理有序命令列表，支持隐式新建模式（`begin()` → `add*()` 方法 → `end()` 返回 `$this`）。

`CircleProgressDelegate` 已从 `CircleProgressBar` 控件提取到 `Rendering` 命名空间，成为独立可测试的组件。

### DesignTokens 主题系统

`DesignTokens` 是一个**不可变**的值对象，代表完整视觉主题：

```php
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\ThemeKey;

$tokens = new DesignTokens();
$tokens = $tokens->with([ThemeKey::PRIMARY->value => 0x3B82F6]);
// $tokens 不变；with() 返回新实例
$text = $tokens->resolve(ThemeKey::TEXT);   // 自动计算对比色
$bg   = $tokens->resolve(ThemeKey::BG);     // 由 PRIMARY 亮度计算
```

- `ThemeKey` 枚举定义了所有 token 键（`PRIMARY`、`BG`、`TEXT`、`BORDER_RADIUS`、`FONT_SIZE` 及组件专属：`SURFACE_*`、`CHART_*`、`CIRCLE_PROGRESS_*`、`TOGGLE_*` 等）
- 内置派生色辅助方法：`shade()` / `tint()` / `alpha()` / `isLight()` / `luminance()`
- `WidgetStyle` trait：为控件类提供 `resolveColor($key, $overrides)` / `resolveStyle($key, $overrides)`
- 扩展 token：`hoverColor` / `disabledColor` wash、`focusRing`（外发光）、`hairline`（1px 边框）、`DARK` 主题预设

### WidgetRenderer 注册表

`WidgetRenderer` 是一个接口，通过命令管线绘制控件：

```php
interface WidgetRenderer
{
    public function render(CommandList $cmds, array $bounds, array $state): void;
}
```

`RendererFactory` 是静态注册表：

```php
RendererFactory::register(new ButtonRenderer());
RendererFactory::register(new SliderRenderer());
$renderer = RendererFactory::make(ButtonRenderer::class);  // 按类名查找
```

内置渲染器（共 10 个）：
- **基础**：`ButtonRenderer`、`CardRenderer`
- **表单输入**：`CheckboxRenderer`、`RadioRenderer`、`SliderRenderer`、`ProgressRenderer`
- **文本**：`TextFieldRenderer`、`SelectRenderer`

`RendererButton` 是一个**桥接控件**——它扩展 `Composite`，底层包装真实的 libui `Button`，但外观通过 `ButtonRenderer` + `DesignTokens` 绘制。这让您可以混合使用 libui 原生控件和自绘控件。

## 布局引擎 (`src/Layout/`)

两种独立的布局算法，均使用不可变样式对象。

### Flexbox 布局

```php
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\FlexLayout;

$style = (new LayoutStyle())->with([
    'width' => 100, 'height' => 50, 'margin' => 4, 'flexGrow' => 1,
]);
$node = new LayoutNode($style, $children);
$result = FlexLayout::layout($nodes, ['width' => 400, 'height' => 300]);
// 返回定位后的子元素，包含计算后的 x, y, width, height
```

- `LayoutStyle` — 不可变样式对象（width/height/min/max/padding/margin/flexGrow/flexShrink/alignSelf）。`with()` 返回新实例
- `LayoutNode` — 节点树（children/parent），递归计算。`layout()` 接受位置约束，`measure()` 返回子树大小。脏标记 + 缓存
- `FlexLayout::layout($nodes, $constraints)` — 经典 flexbox 算法：尺寸计算 → 主轴排列（flexGrow 均分剩余）→ 交叉轴对齐（stretch/center/flex-start/flex-end）。`flex-wrap` 通过显式 `<br>` 节点实现
- 16 项测试覆盖：尺寸、边距、flexGrow、换行、嵌套、交叉轴、min/max、溢出

### Grid 布局

```php
use Yangweijie\Ui2\Layout\GridStyle;
use Yangweijie\Ui2\Layout\GridLayout;

$gridStyle = (new GridStyle())->with([
    'columns' => ['1fr', '2fr', '1fr'],
    'rows' => ['auto', 'min-content'],
    'gap' => 8,
]);
$result = GridLayout::layout($nodes, $gridStyle, ['width' => 600, 'height' => 400]);
```

- `GridTrack` — 行列轨道定义（固定 px / fr 弹性 / min-content / max-content）。`resolveTrack()` 计算 FR 分配
- `GridStyle` — 不可变样式（columns/rows/gap/placement）。`with()` 返回新实例
- `GridLayout::layout($nodes, $gridStyle, $constraints)` — 轨道计算 → 单元分配 → 间距排列。7 项测试

## 事件系统 (`src/Events/`)

为 Surface 渲染控件设计的统一指针和键盘事件模型。

### PointerEvent

封装 libui `AreaMouseEvent` 为标准模型：

```php
use Yangweijie\Ui2\Events\PointerEvent;

// 由 Surface 从 AreaMouseEvent 内部创建：
$event = new PointerEvent(
    x: 42.0, y: 73.0,
    down: 1, held: 0, up: 0,
    modifiers: ['Shift'],
    clickCount: 1,
    timestamp: microtime(true),
);
```

### KeyboardEvent

封装 libui `AreaKeyEvent`：

```php
use Yangweijie\Ui2\Events\KeyboardEvent;

$event = new KeyboardEvent(
    key: 'a',             // 字符（或 ExtKey 名称）
    modifers: ['Ctrl'],    // 按下的修饰键
    up: false,             // true = 按键释放
);
```

### FocusManager

Tab 顺序导航和焦点跟踪：

```php
use Yangweijie\Ui2\Events\FocusManager;

$fm = new FocusManager();
$fm->requestFocus('button1');
$fm->focusNext();    // tab 到下一个可聚焦元素
$fm->blur();
$focused = $fm->getFocused();  // null 或当前聚焦 ID
```

## 语义 / 无障碍 (`src/Semantics/`)

### WidgetRole

定义控件语义角色的枚举：

```php
use Yangweijie\Ui2\Semantics\WidgetRole;

// Button, Checkbox, Radio, Slider, TextField, Label, Image, List,
// TreeGrid, Tab, ProgressBar, StatusBar
```

### SemanticsNode

描述控件的无障碍属性：

```php
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\Semantics\SemanticsNode;

$node = new SemanticsNode(
    role: WidgetRole::Button,
    label: '提交',
    value: null,
    enabled: true,
    checked: null,              // null = 不可勾选
    pressed: false,
    focused: false,
    selected: false,
    bounds: [0, 0, 100, 40],    // x, y, width, height
    children: [],
);
$flat = $node->flatten();      // 返回拍平列表供屏幕阅读器消费
```

## Surface 架构

`Surface` 是上述所有系统的**集成点**。它扩展 `Composite`，包装单个 libui `Area`，并编排：

1. **布局阶段** — `FlexLayout` 定位所有子控件
2. **渲染阶段** — `RendererFactory` 解析每个子控件的 `WidgetRenderer`，通过 `RenderCommand` 管线生成绘制命令
3. **事件分发** — `Area` 的 `mouse()` / `key()` 回调 → `PointerEvent` / `KeyboardEvent` → 路由到焦点子控件

```
┌────────────────────────────────────────────────┐
│ Surface (Composite → Area + AreaDelegate)       │
│                                                  │
│  ├─ FlexLayout ◄── 布局子控件                    │
│  ├─ RendererFactory ◄── 解析渲染器               │
│  ├─ RenderCommandList ◄── 批量绘制命令          │
│  ├─ FocusManager ◄── 键盘焦点                   │
│  └─ SemanticsNode ◄── 构建无障碍树              │
│                                                  │
│  draw(): FlexLayout→RendererFactory→RenderCmds   │
│  mouse(): AreaMouseEvent→PointerEvent→分发       │
│  key():   AreaKeyEvent→KeyboardEvent→FocusManager │
└────────────────────────────────────────────────┘
```

`Surface` 可放入任何 libui 容器（`Box`、`Form`、`Grid`、`Tab`）。它**不是**基于子窗口的控件。

## 重要：Composite GC 陷阱

临时的 `Composite` 对象（如 `(new SeparatorLine())->root()`）会在语句结束时被 PHP 的 GC 调用 `__destruct()`，进而调用 `uiControlDestroy()` 销毁底层 C 控件，而此时 libui 仍持有对该控件的引用。**始终将 Composite 存储在命名的持久变量中。**

如果遇到 `uiControlVerifySetParent` 错误，原因即在于此。
