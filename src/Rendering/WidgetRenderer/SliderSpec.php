<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn slider (range input).
 *
 * @property-read float $value   Current position, 0..1.
 * @property-read bool  $enabled When false the slider is drawn muted.
 * @property-read bool  $pressed True while the thumb is dragged (visual feedback).
 * @property-read bool  $hovered True while the pointer is over the slider.
 */
final class SliderSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $value = 0.0,
        public readonly bool $enabled = true,
        public readonly bool $pressed = false,
        public readonly bool $hovered = false,
    ) {
    }

    public function type(): string
    {
        return 'slider';
    }
}
