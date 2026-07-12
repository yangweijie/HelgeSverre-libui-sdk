<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class MarkdownSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $text = '',
        public readonly float $size = 14.0,
        public readonly string $color = 'default',
        public readonly string $bg = 'transparent',
        public readonly float $radius = 8.0,
        public readonly float $opacity = 1.0,
    ) {}

    public function type(): string
    {
        return 'markdown';
    }
}
