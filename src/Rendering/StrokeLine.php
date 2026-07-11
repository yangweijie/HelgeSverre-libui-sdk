<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;

/**
 * Draw a single straight line segment — used for checkmarks, dividers, etc.
 */
final class StrokeLine extends RenderCommand
{
    public function __construct(
        public float $x0,
        public float $y0,
        public float $x1,
        public float $y1,
        public Color $color,
        public float $thickness = 1.0,
    ) {
    }
}
