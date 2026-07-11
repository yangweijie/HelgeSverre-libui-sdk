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
