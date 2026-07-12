<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class TimelineItemSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $time = '',
        public readonly string $color = 'primary',
    ) {}

    public function type(): string
    {
        return 'timeline_item';
    }
}
