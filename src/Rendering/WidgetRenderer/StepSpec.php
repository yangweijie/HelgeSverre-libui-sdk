<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class StepSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly bool $completed = false,
        public readonly bool $active = false,
        public readonly string $icon = '',
    ) {}

    public function type(): string
    {
        return 'step';
    }
}
