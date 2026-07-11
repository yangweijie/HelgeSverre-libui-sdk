<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn select / dropdown box.
 *
 * @property-read string $value    Currently selected label (may be empty).
 * @property-read string $placeholder Placeholder shown when value is empty.
 * @property-read bool   $enabled  When false the select is drawn muted.
 * @property-read bool   $hovered  True while the pointer is over the box.
 * @property-read float  $radius   Corner radius.
 */
final class SelectSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = 'Select…',
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'select';
    }
}
