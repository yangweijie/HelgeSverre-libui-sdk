<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ResizableSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $minWidth = 100.0,
        public readonly float $minHeight = 60.0,
        public readonly ?float $maxWidth = null,
        public readonly ?float $maxHeight = null,
    ) {}

    public function type(): string
    {
        return 'resizable';
    }
}
