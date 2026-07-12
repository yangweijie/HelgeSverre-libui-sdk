<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class ReactionsSpec extends WidgetSpec
{
    public function __construct(
        public readonly array $reactions = [],
        public readonly ?int $selected = null,
        public readonly float $size = 16.0,
        public readonly float $pillHeight = 28.0,
        public readonly string $color = 'default',
    ) {}

    public function type(): string
    {
        return 'reactions';
    }
}
