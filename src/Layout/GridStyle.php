<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

/**
 * Grid layout style — a CSS-grid subset expressed as struct fields.
 *
 * Columns and rows are lists of {@see GridTrack} (px / fr / auto). Children are
 * placed by their {@see LayoutNode::$gridCol} / {@see LayoutNode::$gridRow}
 * (0-based) with optional spans; children without explicit placement auto-flow
 * row-major into the next free cell.
 *
 * Consumed by {@see GridLayout}; pairs with {@see FlexLayout} (a node uses one
 * or the other depending on whether {@see LayoutNode::$grid} is set).
 */
final class GridStyle
{
    /**
     * @param list<GridTrack> $columns
     * @param list<GridTrack> $rows
     */
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public float $gap = 0.0,
        public float $padding = 0.0,
    ) {
    }
}
