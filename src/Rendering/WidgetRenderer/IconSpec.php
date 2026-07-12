<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Standalone vector icon. Renders a filled circle as placeholder until a real
 * vector icon pipeline is available.
 */
final class IconSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $name = '',
        public readonly float $size = 16.0,
        public readonly string $color = 'default',
    ) {
    }

    public function type(): string
    {
        return 'icon';
    }
}
