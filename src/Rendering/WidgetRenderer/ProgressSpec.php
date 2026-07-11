<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn linear progress bar.
 *
 * @property-read float $value   Completion, 0..1.
 * @property-read bool  $enabled When false the bar is drawn muted.
 * @property-read bool  $hovered True while the pointer is over the bar.
 * @property-read float $radius  Corner radius of the track.
 */
final class ProgressSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $value = 0.0,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'progress';
    }
}
