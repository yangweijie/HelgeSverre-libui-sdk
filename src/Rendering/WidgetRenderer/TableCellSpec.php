<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

final class TableCellSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $value = '',
        public readonly string $align = 'left',
        public readonly bool $bold = false,
        public readonly bool $monospace = false,
    ) {}

    public function type(): string
    {
        return 'table_cell';
    }
}
