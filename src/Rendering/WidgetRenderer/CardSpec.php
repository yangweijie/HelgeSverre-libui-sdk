<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn surface/card.
 *
 * @property-read bool  $bordered  Draw a hairline border around the surface.
 * @property-read bool  $hovered   True while the pointer is over the surface.
 * @property-read float $radius    Corner radius in pixels.
 * @property-read float $elevation Fake shadow strength, 0..1 (libui has no real blur,
 *                                 so elevation is rendered as an offset low-alpha rect).
 */
final class CardSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $bordered = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 12.0,
        public readonly float $elevation = 0.0,
    ) {
    }

    public function type(): string
    {
        return 'card';
    }
}
