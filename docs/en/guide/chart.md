# Chart Component

`Yangweijie\Ui2\ChartV2` is a Chart.js-style chart component, **implemented entirely with libui's `Area` custom drawing — no third-party charting library required**. It reuses this package's `DrawContext` builder and `Loop::repeat()` animation driver, and supports common chart types, dynamic data updates, axes/legend/grid/value-labels, configurable themes, and the `ChartWidget` wrapper for interactive hover/tooltip support.

## Features

- **Chart types**: Line, Bar, Area, Pie, Doughnut, Scatter
- **Pure custom drawing**: all graphics use native `fillRect` / `fillPath` / `strokeLine` / `drawString` / `fillArc`
- **Dynamic updates**: `setData()` + `redraw()` triggers re-render
- **Full annotations**: nice-number auto ticks, grid lines, axes, legend, value labels
- **Themes**: built-in light / dark presets, switchable via `applyTheme()`
- **ChartWidget**: wraps ChartRenderer in an AreaDelegate for mouse hover/tooltip support

## Quick Start

```php
use Yangweijie\Ui2\ChartV2\ChartWidget;
use Yangweijie\Ui2\ChartV2\ChartData;
use Yangweijie\Ui2\ChartV2\ChartSeries;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Libui\Area;
use Libui\Window;

$data = ChartData::create([
    new ChartSeries('Revenue', 'bar', [
        ['value' => 65.0], ['value' => 78.0], ['value' => 52.0],
    ], 0x3B82F6),
    new ChartSeries('Cost', 'bar', [
        ['value' => 42.0], ['value' => 55.0], ['value' => 38.0],
    ], 0xEF4444),
], ['Jan', 'Feb', 'Mar']);

$tokens = new DesignTokens();
$chart = new ChartWidget(null, $data, $tokens);
$area = new Area($chart);
$chart->bindArea($area);

$win = new Window('Chart Demo', 600, 400, true);
$win->setChild($area);
```

## Data Structures

### ChartData

```php
$data = ChartData::create($series, $labels);
$data->title = 'Sales Data';
$data->type = 'bar';           // 'line' | 'bar' | 'area' | 'pie' | 'doughnut' | 'scatter'
$data->theme = 'light';        // 'light' | 'dark'
$data->showValueLabels = true; // show values on bars/points
$data->showGrid = true;
$data->showLegend = true;
```

### ChartSeries

```php
$series = new ChartSeries(
    label:  'Revenue',
    type:   'bar',       // 'line' | 'bar' | 'area' | 'scatter'
    data:   [['value' => 65.0], ['value' => 78.0]],
    color:  0x3B82F6,   // optional hex color
);
$series->lineWidth = 2.0;
$series->pointRadius = 4.0;
$series->barWidth = 24.0;
```

## ChartWidget API

```php
$chart->setType('bar');           // switch chart type
$chart->setTitle('New Title');    // update title
$chart->applyTheme('dark');       // switch theme
$chart->setData($series);         // update data
$chart->setLabels($labels);       // update labels
$chart->palette(0xFF0000, ...);   // set palette colors
$chart->redraw();                 // force redraw
```

## Examples

```bash
# Interactive demo (requires GUI)
php examples/chart-v2-demo.php

# Automated tests
php vendor/bin/pest tests/ChartTest.php
```

`examples/chart-v2-demo.php` provides: type switching, random data generation, theme toggle, palette recoloring, and value label toggle.
