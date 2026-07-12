<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class SplitSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $direction = 'horizontal',
        public readonly float $ratio = 0.5,
        public readonly float $dividerWidth = 4.0,
        public readonly bool $bordered = true,
    ) {}

    public function type(): string
    {
        return 'split';
    }
}
