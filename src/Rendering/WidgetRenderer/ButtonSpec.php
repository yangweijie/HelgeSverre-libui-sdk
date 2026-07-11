<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn push button.
 *
 * @property-read string $label    Text shown on the button ('' = icon-only).
 * @property-read string $variant  One of: 'filled' | 'soft' | 'outline'.
 * @property-read bool   $enabled  When false the button is drawn muted.
 * @property-read bool   $pressed  True while the pointer is held down (visual feedback).
 * @property-read bool   $hovered  True while the pointer is over the button (hover wash).
 * @property-read float  $radius   Corner radius in pixels.
 */
final class ButtonSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly string $variant = 'filled',
        public readonly bool $enabled = true,
        public readonly bool $pressed = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 8.0,
    ) {
    }

    public function type(): string
    {
        return 'button';
    }
}
