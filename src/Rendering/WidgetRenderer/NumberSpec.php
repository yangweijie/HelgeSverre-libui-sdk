<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn numeric text field.
 *
 * Mirrors {@see TextFieldSpec} plus optional numeric bounds. The renderer draws
 * it identically to a text field; numeric filtering/clamping is the control's
 * responsibility (or handled by the host Surface before syncing the value).
 *
 * @property-read string $value     Current text (may be empty).
 * @property-read string $placeholder Placeholder shown when value is empty.
 * @property-read bool   $enabled   When false the field is drawn muted.
 * @property-read bool   $focused   When true a primary border is drawn.
 * @property-read bool   $hovered   True while the pointer is over the field.
 * @property-read float  $radius    Corner radius.
 * @property-read float|null $min   Optional lower bound (hint only).
 * @property-read float|null $max   Optional upper bound (hint only).
 * @property-read float|null $step  Optional step (hint only).
 */
final class NumberSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly ?object $control = null,
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly ?float $step = null,
    ) {
    }

    public function type(): string
    {
        return 'number_field';
    }
}
