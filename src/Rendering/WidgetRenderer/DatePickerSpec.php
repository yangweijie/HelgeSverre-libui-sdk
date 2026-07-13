<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn date picker.
 *
 * Renders as a read-only field showing the selected date (or a placeholder)
 * with a trailing chevron, exactly like a native date input. The actual
 * selection happens in an OS modal — wire it up via
 * `$surface->onClick('nodeId', fn () => DatePickerDialog::pick($window))`.
 *
 * @property-read string $value       Selected date string (e.g. "2026-07-13"), or '' when unset.
 * @property-read string $placeholder Shown when $value is empty.
 * @property-read bool   $enabled     When false the field is drawn muted.
 * @property-read bool   $focused     When true a primary border is drawn.
 * @property-read bool   $hovered     True while the pointer is over the field.
 * @property-read bool   $pressed     True while the pointer is held down.
 * @property-read float  $radius      Corner radius.
 */
final class DatePickerSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '选择日期',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly bool $pressed = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'date_picker';
    }
}
