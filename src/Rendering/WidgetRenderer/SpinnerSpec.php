<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class SpinnerSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $size = 24.0,
        public readonly float $thickness = 2.5,
        public readonly string $color = 'primary',
    ) {}

    public function type(): string
    {
        return 'spinner';
    }
}
