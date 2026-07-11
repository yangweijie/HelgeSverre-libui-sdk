<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn radio button.
 *
 * @property-read bool   $selected Whether the radio is the active option.
 * @property-read bool   $enabled  When false the radio is drawn muted.
 * @property-read bool   $hovered  True while the pointer is over the control.
 * @property-read string $label    Optional label drawn to the right.
 */
final class RadioSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $selected = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly string $label = '',
    ) {
    }

    public function type(): string
    {
        return 'radio';
    }
}
