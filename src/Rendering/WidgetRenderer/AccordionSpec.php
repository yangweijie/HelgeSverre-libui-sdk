<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class AccordionSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $multiple = false,
        public readonly bool $bordered = true,
        public readonly float $radius = 8.0,
    ) {}

    public function type(): string
    {
        return 'accordion';
    }
}
