<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn tab (one entry in a tab strip).
 *
 * @property-read string $label   Tab caption.
 * @property-read bool   $active  True when this is the visible tab.
 * @property-read bool   $enabled When false the tab is drawn muted.
 * @property-read bool   $hovered True while the pointer is over the tab.
 * @property-read float  $radius  Corner radius (used for the hover wash).
 */
final class TabSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly bool $active = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
        public readonly bool $closable = false,
    ) {
    }

    public function type(): string
    {
        return 'tab';
    }
}
