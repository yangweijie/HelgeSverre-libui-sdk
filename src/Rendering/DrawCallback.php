<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Draw\DrawContext;

/**
 * A render command that invokes a user-supplied draw callback.
 *
 * Used by CanvasSpec / CanvasRenderer to embed arbitrary drawing (charts,
 * games, custom visualizations) inside a Surface's LayoutNode tree.
 * The callback receives the DrawContext and the allocated width/height.
 */
final class DrawCallback extends RenderCommand
{
    /**
     * @param callable(DrawContext, float, float): void $callback
     */
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
        /**
     * @var \Closure(DrawContext, float, float): void
     */
    public readonly \Closure $callback,
    ) {
    }
}
