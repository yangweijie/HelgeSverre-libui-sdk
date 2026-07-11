<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for one self-drawn table row (header or data).
 *
 * The column layout is carried on the spec (relative widths + the cell strings
 * in the same order) so the renderer can position each cell without knowing
 * anything about the table's overall structure — a single row is still a leaf
 * node that hit-tests, hovers and selects as one unit.
 *
 * @property-read list<string> $cells    Cell strings, one per column, in order.
 * @property-read list<float>  $widths   Relative column widths, parallel to $cells.
 * @property-read bool         $header   True for the (bold, underlined) header row.
 * @property-read bool         $selected True when the row is the active selection.
 * @property-read bool         $enabled  When false the row is drawn muted.
 * @property-read bool         $hovered  True while the pointer is over the row.
 * @property-read float        $radius   Corner radius of the row's highlight.
 */
final class TableRowSpec extends WidgetSpec
{
    /**
     * @param list<string> $cells
     * @param list<float>  $widths
     */
    public function __construct(
        public readonly array $cells = [],
        public readonly array $widths = [],
        public readonly bool $header = false,
        public readonly bool $selected = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 0.0,
    ) {
    }

    public function type(): string
    {
        return 'table_row';
    }
}
