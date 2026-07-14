# Drawing

The patched `DrawContext` provides a fluent builder pattern for 2D drawing:

```php
$context->fillRect(10, 10, 100, 50, $brush);
$context->strokeCircle(60, 80, 30, $strokeParams);
$context->fillPolygon([10, 20, 30], [10, 40, 10], $brush);

// Save/restore transform state
$context->withSave(function (DrawContext $ctx) {
    $ctx->translate(50, 50);
    $ctx->fillRect(0, 0, 20, 20, $brush);
});

// Measure and draw text
$context->drawString('Hello', 10, 10, $font, $brush);
```

## Path Helpers

The patched `Path` adds convenience methods:

```php
$path->wedge(100, 100, 50, 0, M_PI_2);          // Pie slice
$path->polygon([10, 50, 90], [10, 90, 10]);     // Triangle
$path->roundedRect(10, 10, 100, 50, 10);        // Rounded corners
$path->bezierThrough([10, 40, 90], [50, 10, 50]); // Smooth curve
```

## RenderCommand Pipeline

For structured drawing with command batching, use the `RenderCommand` pipeline (see [Architecture](/en/guide/architecture)):

```php
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\CommandExecutor;

$cmds = (new RenderCommandList())
    ->begin()
    ->addBoxShadow(2, 2, 8, [0, 0, 0, 0.2])
    ->addFill(0x3B82F6)
    ->addRoundedRect(10, 10, 100, 50, 8)
    ->addDrawString('Hello', 10, 30, $font, 0xFFFFFF)
    ->end();

CommandExecutor::execute($drawContext, $cmds->getCommands());
```

This is the foundation of the `WidgetRenderer` system — see [Rendering Engine](/en/guide/architecture#rendering-engine-srcrendering) for details.

## CanvasSpec — Custom Drawing in Surface Layout

`CanvasSpec` embeds arbitrary `DrawContext` drawing callbacks into a Surface's `LayoutNode` tree. This lets you draw charts, games, custom visualizations, or any other content alongside standard widgets (labels, buttons, sliders) in the same Surface.

### Usage

```php
use Yangweijie\Ui2\Rendering\WidgetRenderer\CanvasSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Widgets\Surface;

$chart = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));
        // Draw a line chart, bar chart, game board, etc.
        foreach ($data as $i => $v) {
            $x = ($i / (count($data) - 1)) * $w;
            $y = $h - ($v / $max) * $h;
            $ctx->fillCircle($x, $y, 4.0, Brush::rgb(0x3B82F6));
        }
    },
    background: 0x1E293B,  // optional: fills background before callback
);

$layout = LayoutNode::column(gap: 8, padding: 12)
    ->child(LayoutNode::leaf('title', new LabelSpec('Chart'), height: 30.0))
    ->child(LayoutNode::leaf('canvas', $chart, height: 200.0))
    ->child(LayoutNode::leaf('footer', new LabelSpec('Bottom text'), height: 20.0));

$surface = new Surface($layout);
```

### How it Works

1. `CanvasSpec` holds a `\Closure(DrawContext, float, float): void` callback
2. `CanvasRenderer` wraps it in a `DrawCallback` render command
3. `CommandExecutor::dispatch()` invokes the callback with the DrawContext and allocated width/height
4. The Surface's `withSave()` + `transform()` positions the DrawContext at the node's coordinates, so `(0, 0)` = top-left of the node's allocated rect

### Key Points

- **No Surface modification needed** — CanvasSpec is a pure data-layer extension
- **Callback receives translated DrawContext** — draw at `(0, 0)` to fill the node's area
- **Mixed with any spec** — CanvasSpec leaves coexist with LabelSpec, ButtonSpec, etc. in the same tree
- **Background fill optional** — pass `background: 0xRRGGBB` or `null` for transparent
- **Type**: `\Closure`, not `callable` — PHP 8.5 does not allow `callable` as a readonly property type

### Example: Animated Progress Bar

```php
$progress = 0.0;

$bar = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h) use (&$progress): void {
        $barH = 16.0;
        $y = ($h - $barH) / 2.0;

        $ctx->fillRect(0, $y, $w, $barH, Brush::rgb(0x1E293B));  // track
        $ctx->fillRect(0, $y, $w * $progress, $barH, Brush::rgb(0x3B82F6));  // fill
    },
);

// Animate
Loop::repeat(50, function () use (&$progress, $surface): bool {
    $progress += 0.01;
    if ($progress > 1.0) $progress = 0.0;
    $surface->redraw();
    return true;
});
```
