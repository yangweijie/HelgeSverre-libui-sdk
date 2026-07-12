<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class AlertSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $variant = 'info',
        public readonly bool $closable = true,
        public readonly string $title = '',
    ) {}

    public function type(): string
    {
        return 'alert';
    }
}
