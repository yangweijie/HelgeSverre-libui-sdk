<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class StepperSpec extends WidgetSpec
{
    public function __construct(
        public readonly int $steps = 3,
        public readonly int $current = 0,
        public readonly string $variant = 'numbered',
        public readonly float $size = 24.0,
        public readonly string $color = 'primary',
        public readonly float $gap = 8.0,
    ) {}

    public function type(): string
    {
        return 'stepper';
    }
}
