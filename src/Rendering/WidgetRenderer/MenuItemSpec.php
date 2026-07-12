<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class MenuItemSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly string $icon = '',
        public readonly string $shortcut = '',
        public readonly bool $enabled = true,
        public readonly bool $danger = false,
    ) {}

    public function type(): string
    {
        return 'menu_item';
    }
}
