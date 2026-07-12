<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;

/**
 * A filled arc / pie-slice (wedge) segment.
 *
 * Uses Path::wedge internally when center is provided (pie-chart style),
 * or Path::arc (full arc fill) when no center is needed.
 */
final class FillArc extends RenderCommand
{
    public function __construct(
        public float $cx,
        public float $cy,
        public float $radius,
        public float $startAngle,
        public float $sweep,
        public Color $color,
        /** True = draw a wedge (pie slice from centre). False = arc fill only. */
        public bool $wedge = true,
    ) {
    }
}
