<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class TooltipSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $text = '',
        public readonly string $placement = 'top',
    ) {}

    public function type(): string
    {
        return 'tooltip';
    }
}
