<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn search field (.search_field): a text field with
 * a leading magnifier glyph and a trailing clear ("×") button shown when there
 * is text.
 *
 * @property-read string $value       Current text.
 * @property-read string $placeholder Placeholder shown when value is empty.
 * @property-read bool   $enabled     When false the field is drawn muted.
 * @property-read bool   $focused     When true a primary border is drawn.
 * @property-read bool   $hovered     True while the pointer is over the field.
 * @property-read bool   $showClear   When true the trailing clear button is shown.
 */
final class SearchFieldSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = 'Search…',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly bool $showClear = false,
        public readonly bool $imeActive = false,
        public readonly ?object $control = null,
    ) {
    }

    public function type(): string
    {
        return 'search_field';
    }
}
