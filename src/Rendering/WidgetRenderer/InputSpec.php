<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class InputSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $placeholder = '',
        public readonly bool $enabled = true,
        public readonly bool $focused = false,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
    ) {}

    public function type(): string
    {
        return 'input';
    }
}
