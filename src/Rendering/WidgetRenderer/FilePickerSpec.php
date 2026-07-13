<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn file picker.
 *
 * Renders as a read-only field showing the selected path (or a placeholder)
 * with a trailing chevron, exactly like a native file input. The actual
 * selection happens in an OS modal — wire it up via
 * `$surface->onClick('nodeId', fn () => FilePickerDialog::pick($window))`.
 *
 * @property-read string $value       Selected file path, or '' when unset.
 * @property-read string $placeholder Shown when $value is empty.
 * @property-read bool   $enabled     When false the field is drawn muted.
 * @property-read bool   $focused     When true a primary border is drawn.
 * @property-read bool   $hovered     True while the pointer is over the field.
 * @property-read bool   $pressed     True while the pointer is held down.
 * @property-read float  $radius      Corner radius.
 */
final class FilePickerSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '选择文件',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly bool $pressed = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'file_picker';
    }
}
