<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ToggleButtonSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly bool $pressed = false,
        public readonly bool $enabled = true,
        public readonly string $variant = 'default',
        public readonly float $radius = 6.0,
    ) {}

    public function type(): string
    {
        return 'toggle_button';
    }
}
