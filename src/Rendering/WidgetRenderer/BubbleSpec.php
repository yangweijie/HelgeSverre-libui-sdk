<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class BubbleSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $placement = 'bottom',
        public readonly float $radius = 12.0,
        public readonly float $elevation = 2.0,
    ) {}

    public function type(): string
    {
        return 'bubble';
    }
}
