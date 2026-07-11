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
| `colors(int ...$hex)` | Custom palette (overrides the named-colour default per instance) |
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

### Colours & Palette

The default palette `ChartConfig::PALETTE_NAMES` is a set of **CSS named colours** (e.g. `slateblue`, `crimson`, `teal`) resolved at runtime to `0xRRGGBB` via `Libui\Color::named()`. This keeps the palette semantic and consistent with every other colour in the app:

```php
use Libui\Color;

Color::tomato();                 // named-colour shortcut
Color::named('rebeccapurple');   // explicit lookup
Color::hsl(210, 0.8, 0.5);      // HSL construction
Color::red()->lerp(Color::blue(), 0.5); // blend two colours (purple)
Color::white()->contrastColor(); // auto-contrast foreground (black / white)
```

`Color` also exposes `withHue / withSaturation / withLightness`, `toHsl()`, `mix()`, `luminance()`, and `isLight()` — handy for gradient brushes, theme transitions, and animation tweens.

To use a custom hex palette instead, call `colors()` (overrides per instance, leaves the default untouched):

```php
(new ChartConfig())->colors(0x123456, 0xABCDEF);
```

When the number of series exceeds the base `PALETTE_NAMES` count (10 by default), `colorAt($i)` no longer simply wraps back onto an earlier colour (which would collide). Instead it derives harmonic light/dark **variants from the base colour's HSL lightness**: starting at the base hue, it alternates lighter / darker and steps further out each "ring" by `paletteVariantStep` (default `0.13`). So even 20–30 series get distinct, stylistically consistent colours without manual specification.

```php
$config->paletteVariantStep(0.16);     // widen the light/dark variant contrast
$series = $config->seriesPalette(24);  // expand the full 24-series palette at once
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

`setTheme()` animates by default: in a GUI environment with a bound `Area`, every themed colour (background / grid / axes / text / tooltip) is tweened smoothly to the new preset over `animationDuration` (default 600ms, easeOutCubic) via `Color::lerp`; headless (no GUI) it switches instantly. You can also pass it explicitly:

```php
$chart->setTheme('dark');                   // follows config->animate (animation on by default)
$chart->setTheme('light', animate: false);  // force an instant switch
```

## Recolouring series (recolor)

`setTheme()` smoothly tweens the *themed* colours (background / grid / text / tooltip). The series themselves (each dataset / slice) are resolved by `colorAt($i)` from the palette and, by default, stay stable per index — so a given series keeps its identity across `setData` calls.

To swap the palette, call `recolor()`, which **also tweens via `Color::lerp`** using the same ease-out cubic curve as the theme switch, but on its own `colorAnimator` so the two never clobber each other:

```php
$chart->recolor(0x111827, 0xef4444, 0x10b981); // new palette; series colours tween to it
$chart->recolor();                              // omit args → revert to the default named palette
```

During the tween each in-between shade is injected into `ChartView::$seriesColors` by `Chart::draw()` and consumed by the renderers (`CartesianRenderer` / `PieRenderer`) in preference to `colorAt()`; once done it falls back to the resolved `colorAt($i)`. Headless (no GUI) switches instantly.

## Hover Tooltip

The tooltip is drawn live in `draw()`: it measures the exact text size with `TextLayout::extents()` so the background box hugs the text with even padding and horizontally centered text. Hit-testing is driven by geometry each renderer fills while drawing:

- Cartesian: `barHitboxes` (bar rects), `points` (data point coords)
- Pie / Doughnut: `pieCenter` / `pieRadius` / `pieInner` / `pieSlices` (sector angles and radii)

A redraw only happens when the hovered target changes, avoiding needless repaints.

The tooltip also draws a **small pointer toward the data point (or slice centroid)**: based on the point's position relative to the bubble box, it auto-attaches to the left / right / top / bottom edge, filled with `fillPolygon` and stroked with `tooltipBorder` so the bubble visually connects to the data.

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

# Automated tests (Pest, 24+ cases: types / zoom / animation / pixel mapping / theme / hover / palette variants / theme tween / series recolor)
php vendor/bin/pest tests/ChartTest.php
```

`examples/chart-demo.php` provides a full demo window: top buttons switch chart type, generate random data (animated), toggle value labels, switch theme, recolor, and reset zoom; a status bar at the bottom shows interaction hints.
