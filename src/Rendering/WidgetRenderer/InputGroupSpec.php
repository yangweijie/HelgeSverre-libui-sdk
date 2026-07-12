<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class InputGroupSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly float $gap = 4.0,
    ) {}

    public function type(): string
    {
        return 'input_group';
    }
}
