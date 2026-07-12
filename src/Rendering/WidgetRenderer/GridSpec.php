<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Column-based grid layout. The grid has a fixed number of columns and a
 * uniform gap between cells. Each child occupies one cell; children flow
 * left-to-right, then top-to-bottom.
 *
 * Purely structural — no visual rendering. The layout engine divides the
 * available width evenly into `columns` slots.
 */
final class GridSpec extends WidgetSpec
{
    public function __construct(
        public readonly int $columns = 2,
        public readonly float $gap = 8.0,
    ) {
    }

    public function type(): string
    {
        return 'grid';
    }
}
