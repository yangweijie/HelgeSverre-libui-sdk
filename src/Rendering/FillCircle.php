<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;

final class FillCircle extends RenderCommand
{
    public function __construct(
        public float $cx,
        public float $cy,
        public float $radius,
        public Color $color,
    ) {
    }
}
