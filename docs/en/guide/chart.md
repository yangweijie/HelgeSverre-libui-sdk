# Chart Component

`Yangweijie\Ui2\Chart` is a Chart.js-style chart component, **implemented entirely with libui's `Area` custom drawing — no third-party charting library required**. It reuses this package's `DrawContext` builder and `Loop::repeat()` animation driver, and supports common chart types, gesture interaction, animated data updates, axes/legend/grid/data-labels, configurable themes, and pluggable renderers.

## Features

- **Chart types**: Line, Bar, Pie, Doughnut, Scatter
- **Pure custom drawing**: all graphics use native `fillRect` / `fillPath` / `strokeLine` / `drawString` / `fillArc`
- **Gestures**: double-click zoom, Shift+drag emulated pinch, drag box-zoom (when not zoomed), drag pan (when zoomed), keyboard `+/-/=` zoom, `0` reset
- **Dynamic updates + animation**: `setData()` triggers an easeOutCubic tween; headless (no GUI) environments sync immediately
- **Full annotations**: nice-number auto ticks, grid lines, axes, legend, value labels, hover tooltip
- **Themes**: built-in light / dark presets, switchable in one call (including tooltip colors)
- **Extensible**: implement `ChartRenderer` and register it with `RendererFactory` to add new chart types

## Quick Start

```php
use Yangweijie\Ui2\Chart\Chart;
use Yangweijie\Ui2\Chart\ChartConfig;
use Yangweijie\Ui2\Chart\ChartType;
use Yangweijie\Ui2\Chart\Dataset;
use Libui\Area;
use Libui\Window;

$chart = new Chart(
    ChartType::Line,
    (new ChartConfig())->title('Monthly Revenue')->showValues(true),
    [
        new Dataset('Revenue', [12, 19, 14, 27, 22, 30, 25]),
        new Dataset('Cost',    [8, 12, 10, 18, 15, 20, 17]),
    ],
);
$chart->setLabels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']);

$win = new Window('Chart Demo', 920, 640, true);
$win->setMargined(true);
$area = new Area($chart);          // AreaDelegate handles Area's events
$win->addChild($area);
$win->show();
```

`Chart` extends `AreaDelegate`, so passing the instance to `new Area($chart)` wires up `draw()` / `mouse()` / `key()` automatically.

## Data Structures

### Dataset

```php
new Dataset(
    label:      'Revenue',              // name shown in legend / tooltip
    data:       [12, 19, 14, null, 22], // values; null = missing (line breaks, bar skipped)
    color:      0x3B82F6,              // optional, overrides palette color
    type:       ChartType::Line,       // optional, per-dataset type override (mixed charts)
    fill:       true,                  // fill under line
    lineWidth:  2.0,
    showPoints: true,
    showValues: null,                  // null = follow global config->showValues
    pointRadius: 3.0,
);
```

### ChartType

```php
ChartType::Line | Bar | Pie | Doughnut | Scatter
// isCartesian() returns false for Pie/Doughnut (no axes)
```

## Configuration (ChartConfig)

All config methods are fluent and return `self`:

| Method / field | Description |
|---|---|
| `title(string)` | Chart title |
| `showLegend(bool, 'right'\|'top'\|'bottom')` | Legend toggle and position |
| `showGrid(bool)` | Grid lines toggle |
| `showValues(?bool)` | Value labels (null = follow global) |
| `animation(float $ms, bool $enabled)` | Animation duration and toggle |
| `zoom(bool $enabled, ?float $max)` | Zoom toggle and max zoom factor |
| `padding(top, right, bottom, left)` | Plot area padding |
| `colors(int ...$hex)` | Custom palette |
| `applyTheme('light'\|'dark')` | Apply a theme preset |

Common assignable fields: `showTitle`, `titleColor`, `titleSize`, `legendColor`, `showAxisX/Y`, `axisColor`, `axisLabelColor`, `axisFontSize`, `yZeroBased`, `panEnabled`, `maxZoom`, `background`, `plotBackground`, `tooltipBackground/Text/Border`, `fontSize`, `fontFamily`.

```php
$config = (new ChartConfig())
    ->title('Sales Trend')
    ->showLegend(true, 'top')
    ->showValues(true)
    ->animation(500, true)
    ->zoom(true, 8.0)
    ->padding(20, 24, 16, 16);
```

## Interaction

> **Note**: libui's `Area` **only forwards draw / mouse / mouseCrossed / dragBroken / key events — there are no native wheel or touch events**. So "pinch" on the desktop is emulated with **Shift + horizontal drag**, while "double-click zoom" uses the native `AreaMouseEvent.count === 2`.

| Action | Behavior |
|---|---|
| Double-click | Zoom 2.2× anchored at cursor; Ctrl+Shift+double-click resets |
| Shift + drag | Pinch zoom (factor from horizontal delta, anchor fixed) |
| Drag when not zoomed | Box-zoom: drag a blue rectangle, release to zoom into it |
| Drag when zoomed | Pan the view |
| Keyboard `+` / `=` | Zoom in 1.3× |
| Keyboard `-` | Zoom out 1/1.3× |
| Keyboard `0` | Reset zoom |
| Hover | Show tooltip (nearest point/bar for cartesian; slice for pie) |

```php
$chart->resetZoom();                 // reset zoom
$chart->setData($datasets, animate: true);  // dynamic update + animation
$chart->setType(ChartType::Bar);     // switch type
$chart->setTheme('dark');            // switch theme
```

## Themes

`ChartConfig::THEMES` ships `light` / `dark` presets (background, grid, axes, text, and tooltip colors). `applyTheme()` safely falls back to `light` for unknown names:

```php
$chart->setTheme('dark');   // equivalent to $chart->getConfig()->applyTheme('dark') then redraw
```

## Hover Tooltip

The tooltip is drawn live in `draw()`: it measures the exact text size with `TextLayout::extents()` so the background box hugs the text with even padding and horizontally centered text. Hit-testing is driven by geometry each renderer fills while drawing:

- Cartesian: `barHitboxes` (bar rects), `points` (data point coords)
- Pie / Doughnut: `pieCenter` / `pieRadius` / `pieInner` / `pieSlices` (sector angles and radii)

A redraw only happens when the hovered target changes, avoiding needless repaints.

## Extensibility

Add a chart type by implementing `ChartRenderer` and registering it:

```php
interface ChartRenderer
{
    public function supports(ChartType $type): bool;
    public function render(DrawContext $ctx, Chart $chart, ChartView $view): void;
}

// Register (RendererFactory is a singleton registry)
RendererFactory::register(new RadarRenderer());
$renderer = RendererFactory::make(ChartType::Radar); // first renderer that supports it
```

`ChartView` maps pixel ↔ data coordinates (`xToPx` / `yToPx` / `pxToX` / `pxToY`), `Scale` produces nice-number ticks, and `ZoomState` owns the zoom domain — cleanly separated for unit testing (see `tests/ChartTest.php`).

## Examples & Tests

```bash
# Visual demo (requires a libui GUI environment)
php examples/chart-demo.php

# Automated tests (Pest, 14 cases: types / zoom / animation / pixel mapping / theme / hover)
php vendor/bin/pest tests/ChartTest.php
```

`examples/chart-demo.php` provides a full demo window: top buttons switch chart type, generate random data (animated), toggle value labels, switch theme, and reset zoom; a status bar at the bottom shows interaction hints.
