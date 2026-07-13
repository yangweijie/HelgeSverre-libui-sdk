<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn text field (input).
 *
 * @property-read string $value       Current text (may be empty).
 * @property-read string $placeholder Placeholder shown when value is empty.
 * @property-read bool   $enabled     When false the field is drawn muted.
 * @property-read bool   $focused     When true a primary border is drawn.
 * @property-read bool   $hovered     True while the pointer is over the field.
 * @property-read float  $radius      Corner radius.
 */
final class TextFieldSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly bool $imeActive = false,
        public readonly ?object $control = null,
    ) {
    }

    public function type(): string
    {
        return 'text_field';
    }
}
