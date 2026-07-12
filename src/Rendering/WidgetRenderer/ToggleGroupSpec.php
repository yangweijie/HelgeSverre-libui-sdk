<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ToggleGroupSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $multi = false,
        public readonly float $gap = 0.0,
    ) {}

    public function type(): string
    {
        return 'toggle_group';
    }
}
