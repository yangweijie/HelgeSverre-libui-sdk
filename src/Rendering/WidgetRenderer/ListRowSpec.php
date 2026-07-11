<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn list row.
 *
 * @property-read string $label     Primary text drawn at the left of the row.
 * @property-read string $subtitle  Optional secondary text drawn under the label.
 * @property-read bool   $selected  True when the row is the active selection.
 * @property-read bool   $enabled   When false the row is drawn muted.
 * @property-read bool   $hovered   True while the pointer is over the row.
 * @property-read float  $radius    Corner radius of the row's highlight.
 */
final class ListRowSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly string $subtitle = '',
        public readonly bool $selected = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'list_row';
    }
}
