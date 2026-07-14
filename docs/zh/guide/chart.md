# 图表组件

`Yangweijie\Ui2\ChartV2` 是一个仿 Chart.js 的图表组件，**完全基于 libui 的 `Area` 自绘实现，不依赖任何第三方图表库**。它复用了本包的 `DrawContext` 构建器与 `Loop::repeat()` 动画驱动，支持常见图表类型、动态数据更新、坐标轴/图例/网格/数值标签、可配置的主题，以及 `ChartWidget` 包装器提供的鼠标悬停和 Tooltip 支持。

## 特性一览

- **图表类型**：折线图（Line）、柱状图（Bar）、面积图（Area）、饼图（Pie）、环形图（Doughnut）、散点图（Scatter）
- **纯自绘**：所有图形用 `fillRect` / `fillPath` / `strokeLine` / `drawString` / `fillArc` 等原生绘制
- **动态更新**：`setData()` + `redraw()` 触发重绘
- **完整标注**：nice-number 自动刻度、网格线、坐标轴、图例、数值标签
- **主题**：内置 light / dark 预设，通过 `applyTheme()` 切换
- **ChartWidget**：将 ChartRenderer 封装在 AreaDelegate 中，支持鼠标悬停和 Tooltip

## 快速开始

```php
use Yangweijie\Ui2\ChartV2\ChartWidget;
use Yangweijie\Ui2\ChartV2\ChartData;
use Yangweijie\Ui2\ChartV2\ChartSeries;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Libui\Area;
use Libui\Window;

$data = ChartData::create([
    new ChartSeries('营收', 'bar', [
        ['value' => 65.0], ['value' => 78.0], ['value' => 52.0],
    ], 0x3B82F6),
    new ChartSeries('成本', 'bar', [
        ['value' => 42.0], ['value' => 55.0], ['value' => 38.0],
    ], 0xEF4444),
], ['1月', '2月', '3月']);

$tokens = new DesignTokens();
$chart = new ChartWidget(null, $data, $tokens);
$area = new Area($chart);
$chart->bindArea($area);

$win = new Window('图表示例', 600, 400, true);
$win->setChild($area);
```

## 数据结构

### ChartData

```php
$data = ChartData::create($series, $labels);
$data->title = '销售数据';
$data->type = 'bar';           // 'line' | 'bar' | 'area' | 'pie' | 'doughnut' | 'scatter'
$data->theme = 'light';        // 'light' | 'dark'
$data->showValueLabels = true; // 在柱/点上显示数值
$data->showGrid = true;
$data->showLegend = true;
```

### ChartSeries

```php
$series = new ChartSeries(
    label:  '营收',
    type:   'bar',       // 'line' | 'bar' | 'area' | 'scatter'
    data:   [['value' => 65.0], ['value' => 78.0]],
    color:  0x3B82F6,   // 可选十六进制颜色
);
$series->lineWidth = 2.0;
$series->pointRadius = 4.0;
$series->barWidth = 24.0;
```

## ChartWidget API

```php
$chart->setType('bar');           // 切换图表类型
$chart->setTitle('新标题');       // 更新标题
$chart->applyTheme('dark');       // 切换主题
$chart->setData($series);         // 更新数据
$chart->setLabels($labels);       // 更新标签
$chart->palette(0xFF0000, ...);   // 设置调色板颜色
$chart->redraw();                 // 强制重绘
```

## 示例

```bash
# 交互式演示（需要 GUI 环境）
php examples/chart-v2-demo.php

# 自动化测试
php vendor/bin/pest tests/ChartTest.php
```

`examples/chart-v2-demo.php` 提供：类型切换、随机数据生成、主题切换、重新配色、数值标签开关。
