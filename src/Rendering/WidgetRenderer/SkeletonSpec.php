<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class SkeletonSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $variant = 'text',
        public readonly ?float $width = null,
        public readonly ?float $height = null,
    ) {}

    public function type(): string
    {
        return 'skeleton';
    }
}
