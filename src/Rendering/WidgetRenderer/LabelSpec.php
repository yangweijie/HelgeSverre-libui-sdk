<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn text label.
 *
 * A label is non-interactive text rendered inside a Surface. It has no
 * background, border, or hit-testing behaviour — it exists purely to label
 * sections of a self-drawn UI.
 *
 * @property-write string $text     Label text (mutable — update and call Surface::redraw()).
 * @property-read  float  $size      Font size in pixels.
 * @property-read  string $align     Text alignment: 'left' | 'center' | 'right'.
 * @property-read  string $color     Token key used to resolve the text colour.
 * @property-read  float  $opacity   Text opacity, 0..1.
 */
final class LabelSpec extends WidgetSpec
{
    public function __construct(
        public string $text = '',
        public readonly float $size = 14.0,
        public readonly string $align = 'left',
        public readonly string $color = 'color.onSurface',
        public readonly float $opacity = 0.85,
    ) {
    }

    public function type(): string
    {
        return 'label';
    }
}
