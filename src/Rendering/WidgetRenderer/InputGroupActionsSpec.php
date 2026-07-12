<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class InputGroupActionsSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $align = 'end',
        public readonly float $gap = 4.0,
    ) {}

    public function type(): string
    {
        return 'input_group_actions';
    }
}
