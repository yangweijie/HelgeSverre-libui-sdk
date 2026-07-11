<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;
use Libui\Draw\StrokeParams;

/**
 * A stroked arc / ring segment.
 *
 * Uses Path::arc (NOT wedge): a full-circle sweep renders as a clean ring with
 * no radial spokes, matching the existing CircleProgressBar rendering. Using
 * DrawContext::strokeArc would be wrong here — it builds a wedge and strokes
 * the two radii back to the centre.
 */
final class StrokeArc extends RenderCommand
{
    public function __construct(
        public float $cx,
        public float $cy,
        public float $radius,
        public float $startAngle,
        public float $sweep,
        public Color $color,
        public StrokeParams $stroke,
    ) {
    }
}
