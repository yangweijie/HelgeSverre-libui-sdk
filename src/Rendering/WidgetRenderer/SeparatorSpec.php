<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual divider — a horizontal or vertical line.
 */
final class SeparatorSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $orientation = 'horizontal',
        public readonly float $thickness = 1.0,
        public readonly string $color = 'default',
    ) {
    }

    public function type(): string
    {
        return 'separator';
    }
}
