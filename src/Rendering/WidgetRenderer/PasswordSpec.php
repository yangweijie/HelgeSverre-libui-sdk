<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn password field.
 *
 * The renderer masks the value with bullets unless {@see $reveal} is set (e.g.
 * a peek/eye toggle). Mirrors {@see TextFieldSpec} otherwise.
 *
 * @property-read string $value     Current text (masked at draw time).
 * @property-read string $placeholder Placeholder shown when value is empty.
 * @property-read bool   $enabled   When false the field is drawn muted.
 * @property-read bool   $focused   When true a primary border is drawn.
 * @property-read bool   $hovered   True while the pointer is over the field.
 * @property-read float  $radius    Corner radius.
 * @property-read bool   $reveal    When true the real value is drawn (peek).
 */
final class PasswordSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly bool $reveal = false,
        public readonly ?object $control = null,
    ) {
    }

    public function type(): string
    {
        return 'password_field';
    }
}
