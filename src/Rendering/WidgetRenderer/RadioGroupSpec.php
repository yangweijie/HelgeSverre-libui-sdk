<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class RadioGroupSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $orientation = 'vertical',
        public readonly float $gap = 6.0,
    ) {}

    public function type(): string
    {
        return 'radio_group';
    }
}
