<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ContextMenuSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $radius = 8.0,
        public readonly float $elevation = 4.0,
        public readonly ?float $width = null,
    ) {}

    public function type(): string
    {
        return 'context_menu';
    }
}
