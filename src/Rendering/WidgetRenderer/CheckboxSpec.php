<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn checkbox.
 *
 * @property-read bool   $checked  Whether the box is ticked.
 * @property-read bool   $enabled  When false the checkbox is drawn muted.
 * @property-read bool   $hovered  True while the pointer is over the control.
 * @property-read string $label    Optional label drawn to the right of the box.
 * @property-read float  $radius   Corner radius of the box.
 */
final class CheckboxSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $checked = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly string $label = '',
        public readonly float $radius = 4.0,
    ) {
    }

    public function type(): string
    {
        return 'checkbox';
    }
}
