<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn multiline text field (.textarea).
 *
 * @property-read string   $value       Current text (may contain newlines).
 * @property-read string   $placeholder Shown when value is empty.
 * @property-read bool     $enabled     When false the field is drawn muted.
 * @property-read bool     $focused     When true a primary border is drawn.
 * @property-read bool     $hovered     True while the pointer is over the field.
 * @property-read float    $radius      Corner radius.
 * @property-read float    $scrollY     Vertical scroll offset (px).
 * @property-read int      $cursor      Caret position as a codepoint offset into $value.
 * @property-read float    $lineHeight  Line spacing (px).
 * @property-read float    $fontSize    Text size (px).
 * @property-read TextAreaControl|null $control Back-reference to the owning control (set by TextAreaControl).
 */
final class TextAreaSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly bool $imeActive = false,
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly float $scrollY = 0.0,
        public readonly int $cursor = 0,
        public readonly float $lineHeight = 20.0,
        public readonly float $fontSize = 14.0,
        public readonly \Yangweijie\Ui2\Widgets\TextAreaControl|null $control = null,
    ) {
    }

    public function type(): string
    {
        return 'text_area';
    }
}
