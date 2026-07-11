<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;
use Libui\Draw\StrokeParams;

final class StrokeRoundedRect extends RenderCommand
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public float $radius,
        public Color $color,
        public StrokeParams $stroke,
    ) {
    }
}
