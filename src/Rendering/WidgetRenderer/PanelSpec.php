<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Opaque floating panel background (used for dropdown menus, combobox panels,
 * context menus, etc.). Unlike {@see CardSpec}, it does not react to hover, so
 * individual items inside the panel can provide their own hover feedback.
 */
final class PanelSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $bordered = true,
        public readonly float $radius = 8.0,
        public readonly float $elevation = 0.5,
    ) {
    }

    public function type(): string
    {
        return 'panel';
    }
}
