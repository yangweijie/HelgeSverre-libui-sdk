<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class SwitchSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly bool $pressed = false,
    ) {}

    public function type(): string
    {
        return 'switch';
    }
}
