<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class SpanSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $text = '',
        public readonly float $size = 14.0,
        public readonly string $color = 'default',
        public readonly float $opacity = 1.0,
    ) {}

    public function type(): string
    {
        return 'span';
    }
}
