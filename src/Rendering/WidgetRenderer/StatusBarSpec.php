<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class StatusBarSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $text = '',
        public readonly string $variant = 'default',
        public readonly float $height = 28.0,
    ) {}

    public function type(): string
    {
        return 'status_bar';
    }
}
