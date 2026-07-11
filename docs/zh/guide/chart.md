# 图表组件

`Yangweijie\Ui2\Chart` 是一个仿 Chart.js 的图表组件，**完全基于 libui 的 `Area` 自绘实现，不依赖任何第三方图表库**。它复用了本包的 `DrawContext` 构建器与 `Loop::repeat()` 动画驱动，支持常见图表类型、手势交互、动态数据动画、坐标轴/图例/网格/数据标签，以及可配置的主题与可插拔的渲染器。

## 特性一览

- **图表类型**：折线图（Line）、柱状图（Bar）、饼图（Pie）、环形图（Doughnut）、散点图（Scatter）
- **纯自绘**：所有图形用 `fillRect` / `fillPath` / `strokeLine` / `drawString` / `fillArc` 等原生绘制
- **手势交互**：双击缩放、Shift+拖拽模拟双指捏合、拖拽框选放大（未缩放时）、拖拽平移（已缩放时）、键盘 `+/-/=` 缩放、`0` 复位
- **动态更新 + 动画**：`setData()` 触发 easeOutCubic 补间过渡；无 GUI 环境（测试）下即时同步
- **完整标注**：nice-number 自动刻度、网格线、坐标轴、图例、数值标签、悬停 tooltip
- **主题**：内置 light / dark 预设，一键切换（含 tooltip 配色）
- **可扩展**：实现 `ChartRenderer` 接口并注册到 `RendererFactory` 即可新增图表类型

## 快速开始

```php
use Yangweijie\Ui2\Chart\Chart;
use Yangweijie\Ui2\Chart\ChartConfig;
use Yangweijie\Ui2\Chart\ChartType;
use Yangweijie\Ui2\Chart\Dataset;
use Libui\Area;
use Libui\Window;

$chart = new Chart(
    ChartType::Line,
    (new ChartConfig())->title('月度营收')->showValues(true),
    [
        new Dataset('营收', [12, 19, 14, 27, 22, 30, 25]),
        new Dataset('成本', [8, 12, 10, 18, 15, 20, 17]),
    ],
);
$chart->setLabels(['1月', '2月', '3月', '4月', '5月', '6月', '7月']);

$win = new Window('Chart Demo', 920, 640, true);
$win->setMargined(true);
$area = new Area($chart);          // AreaDelegate 作为 Area 的事件处理器
$win->addChild($area);
$win->show();
```

`Chart` 继承自 `AreaDelegate`，直接把实例交给 `new Area($chart)` 即可接管 `draw()` / `mouse()` / `key()` 事件。

## 数据结构

### Dataset

```php
new Dataset(
    label:    '营收',                 // 图例与 tooltip 显示名
    data:     [12, 19, 14, null, 22], // 数值数组，null 表示缺失（折线断裂、柱状跳过）
    color:    0x3B82F6,              // 可选，覆盖调色板取色
    type:     ChartType::Line,       // 可选，单数据集覆盖图表类型（混合图）
    fill:     true,                  // 折线下方填充
    lineWidth: 2.0,
    showPoints: true,
    showValues: null,                // null = 跟随全局 config->showValues
    pointRadius: 3.0,
);
```

### ChartType

```php
ChartType::Line | Bar | Pie | Doughnut | Scatter
// isCartesian() 对 Pie/Doughnut 返回 false（无坐标轴）
```

## 配置（ChartConfig）

所有配置项均为流式 API，返回 `self`：

| 方法 / 字段 | 说明 |
|---|---|
| `title(string)` | 图表标题 |
| `showLegend(bool, 'right'\|'top'\|'bottom')` | 图例开关与位置 |
| `showGrid(bool)` | 网格线开关 |
| `showValues(?bool)` | 数值标签（null = 跟随全局） |
| `animation(float $ms, bool $enabled)` | 动画时长与开关 |
| `zoom(bool $enabled, ?float $max)` | 缩放开关与最大放大倍数 |
| `padding(top, right, bottom, left)` | 绘图区内边距 |
| `colors(int ...$hex)` | 自定义调色板 |
| `applyTheme('light'\|'dark')` | 套用主题预设 |

常用字段（可直接赋值）：`showTitle`、`titleColor`、`titleSize`、`legendColor`、`showAxisX/Y`、`axisColor`、`axisLabelColor`、`axisFontSize`、`yZeroBased`、`panEnabled`、`maxZoom`、`background`、`plotBackground`、`tooltipBackground/Text/Border`、`fontSize`、`fontFamily`。

```php
$config = (new ChartConfig())
    ->title('销售趋势')
    ->showLegend(true, 'top')
    ->showValues(true)
    ->animation(500, true)
    ->zoom(true, 8.0)
    ->padding(20, 24, 16, 16);
```

## 交互

> **重要**：libui 的 `Area` **只转发 draw / mouse / mouseCrossed / dragBroken / key 事件，没有原生滚轮 / 触摸事件**。因此“双指捏合”在桌面端是通过 **Shift + 横向拖拽** 模拟的，而“双击缩放”直接复用原生 `AreaMouseEvent.count === 2`。

| 操作 | 行为 |
|---|---|
| 双击 | 以光标处为锚点放大 2.2×；Ctrl+Shift+双击复位 |
| Shift + 拖拽 | 捏合缩放（因子由横向位移算出，锚点固定） |
| 未缩放时拖拽 | 框选放大：拖出蓝色选框，松手缩放进框内 |
| 已缩放时拖拽 | 平移视图 |
| 键盘 `+` / `=` | 放大 1.3× |
| 键盘 `-` | 缩小 1/1.3× |
| 键盘 `0` | 复位缩放 |
| 悬停 | 显示 tooltip（折线/柱命中最近点/柱；饼图命中扇区） |

```php
$chart->resetZoom();                 // 复位缩放
$chart->setData($datasets, animate: true);  // 动态更新 + 动画
$chart->setType(ChartType::Bar);     // 切换类型
$chart->setTheme('dark');            // 切换主题
```

## 主题

`ChartConfig::THEMES` 内置 `light` / `dark` 两套预设（含背景、网格、坐标轴、文字与 tooltip 配色）。`applyTheme()` 未知名称会安全回退到 `light`：

```php
$chart->setTheme('dark');   // 等价于 $chart->getConfig()->applyTheme('dark') 后重绘
```

## 悬停 Tooltip

tooltip 在 `draw()` 阶段实时绘制：用 `TextLayout::extents()` 实测文字宽高，使背景框与文字等 padding 贴合、文字水平居中。命中检测由各渲染器在绘制时填充的几何信息驱动：

- 笛卡尔图：`barHitboxes`（柱矩形）、`points`（数据点坐标）
- 饼 / 环：`pieCenter` / `pieRadius` / `pieInner` / `pieSlices`（扇区角度与半径）

命中目标变化时才会 `redraw()`，避免无谓重绘。

## 可扩展性

新增图表类型只需实现 `ChartRenderer` 接口并注册：

```php
interface ChartRenderer
{
    public function supports(ChartType $type): bool;
    public function render(DrawContext $ctx, Chart $chart, ChartView $view): void;
}

// 注册（RendererFactory 为单例注册表）
RendererFactory::register(new RadarRenderer());
$renderer = RendererFactory::make(ChartType::Radar); // 返回第一个 supports 的渲染器
```

`ChartView` 负责像素 ↔ 数据坐标映射（`xToPx` / `yToPx` / `pxToX` / `pxToY`），`Scale` 负责 nice-number 刻度，`ZoomState` 负责缩放域——三者职责分离，便于单元测试（见 `tests/ChartTest.php`）。

## 示例与测试

```bash
# 可视化演示（需要 libui GUI 环境）
php examples/chart-demo.php

# 自动化测试（Pest，14 个用例：类型/缩放/动画/像素映射/主题/悬停）
php vendor/bin/pest tests/ChartTest.php
```

`examples/chart-demo.php` 提供了完整的演示窗口：顶部按钮切换图表类型、随机数据（带动画）、数值标签开关、主题切换、重置缩放，底部状态栏显示交互提示。
