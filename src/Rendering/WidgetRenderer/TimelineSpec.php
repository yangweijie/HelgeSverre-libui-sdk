<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class TimelineSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $align = 'left',
        public readonly float $size = 24.0,
        public readonly float $gap = 12.0,
        public readonly string $color = 'default',
    ) {}

    public function type(): string
    {
        return 'timeline';
    }
}
