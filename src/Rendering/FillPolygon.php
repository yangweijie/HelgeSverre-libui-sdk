<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;

/**
 * Fill a closed polygon from a list of [x, y] points — used for arrows, carets,
 * and other small solid shapes that aren't rounded rects or circles.
 *
 * @var list<array{float,float}> $points
 */
final class FillPolygon extends RenderCommand
{
    /**
     * @param list<array{float,float}> $points
     */
    public function __construct(
        public array $points,
        public Color $color,
    ) {
    }
}
