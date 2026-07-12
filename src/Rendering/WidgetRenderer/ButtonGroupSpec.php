<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ButtonGroupSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $variant = 'default',
        public readonly float $gap = 0.0,
    ) {}

    public function type(): string
    {
        return 'button_group';
    }
}
