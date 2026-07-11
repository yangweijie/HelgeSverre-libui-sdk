<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

/**
 * A CSS-grid layout engine — computes pixel rects for a {@see LayoutNode} tree
 * whose root carries a {@see GridStyle}.
 *
 * Resolves px tracks directly, distributes leftover space across fr tracks
 * proportionally, and treats auto tracks as 0 in v1 (no content measurement).
 * Children with explicit gridCol/gridRow are placed there (with spans); the
 * rest auto-flow row-major into the next free 1×1 cell. Pure and headless.
 *
 * Usage:
 *   $root = (new LayoutNode())->child(...)->child(...);
 *   $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], [GridTrack::px(40)]);
 *   GridLayout::layout($root, 0, 0, 200, 80);
 */
final class GridLayout
{
    public static function layout(LayoutNode $root, float $x, float $y, float $w, float $h): void
    {
        $root->x = $x;
        $root->y = $y;
        $root->w = $w;
        $root->h = $h;

        $gs = $root->grid;
        if ($gs === null || $gs->columns === []) {
            return;
        }

        $gap = $gs->gap;
        $pad = $gs->padding;
        $contentW = $w - 2 * $pad;
        $contentH = $h - 2 * $pad;

        $colWidths = self::resolveTracks($gs->columns, $contentW, $gap);
        $rowCount = count($gs->rows);
        $autoRows = $rowCount === 0;
        // If rows are unspecified, infer a row count from auto-placement capacity.
        if ($autoRows) {
            $colCount = count($colWidths);
            $rowCount = $colCount > 0 ? (int) ceil(count($root->children) / $colCount) : 1;
        }
        $rowHeights = $autoRows
            ? array_fill(0, $rowCount, $contentH / max(1, $rowCount))
            : self::resolveTracks($gs->rows, $contentH, $gap);

        // Build an occupancy map for auto-placement (1×1 only).
        $occupied = array_fill(0, $rowCount * count($colWidths), false);

        $autoCursor = 0;
        foreach ($root->children as $child) {
            $col = $child->gridCol;
            $row = $child->gridRow;
            if ($col === null || $row === null) {
                [$col, $row] = self::nextFreeCell($occupied, count($colWidths), $rowCount, $autoCursor);
                $autoCursor = $row * count($colWidths) + $col + 1;
            }
            $col = max(0, min($col, count($colWidths) - 1));
            $row = max(0, min($row, count($rowHeights) - 1));

            $cx = $pad + self::spanExtent($colWidths, $col, $child->colSpan, $gap);
            $cy = $pad + self::spanExtent($rowHeights, $row, $child->rowSpan, $gap);
            $cw = self::spanSize($colWidths, $col, $child->colSpan, $gap);
            $ch = self::spanSize($rowHeights, $row, $child->rowSpan, $gap);

            $child->x = $x + $cx;
            $child->y = $y + $cy;
            $child->w = $cw;
            $child->h = $ch;

            if ($child->isContainer()) {
                self::recurse($child);
            }
        }
    }

    /** Recurse into a child: grid if it has a grid style, otherwise flex. */
    private static function recurse(LayoutNode $node): void
    {
        if ($node->grid !== null && $node->grid->columns !== [] && $node->children !== []) {
            self::layout($node, $node->x, $node->y, $node->w, $node->h);
        } elseif ($node->children !== []) {
            FlexLayout::layoutChildren($node);
        }
    }

    /**
     * Resolve a list of tracks against an available size: px tracks take their
     * fixed size, fr tracks share the leftover proportionally, auto tracks are 0.
     *
     * @param list<GridTrack> $tracks
     * @return list<float>
     */
    private static function resolveTracks(array $tracks, float $available, float $gap): array
    {
        $n = count($tracks);
        $sizes = array_fill(0, $n, 0.0);
        $used = 0.0;
        $totalFr = 0.0;
        foreach ($tracks as $i => $t) {
            if ($t->type === GridTrack::PX) {
                $sizes[$i] = $t->value;
                $used += $t->value;
            } elseif ($t->type === GridTrack::FR) {
                $totalFr += $t->value;
            }
        }
        $free = $available - $used - $gap * max(0, $n - 1);
        if ($free > 0 && $totalFr > 0) {
            foreach ($tracks as $i => $t) {
                if ($t->type === GridTrack::FR) {
                    $sizes[$i] = $free * ($t->value / $totalFr);
                }
            }
        }

        return $sizes;
    }

    /** Sum of track sizes from $start over $span, plus interior gaps. */
    private static function spanSize(array $sizes, int $start, int $span, float $gap): float
    {
        $span = max(1, $span);
        $end = min(count($sizes), $start + $span);
        $sum = 0.0;
        for ($i = $start; $i < $end; $i++) {
            $sum += $sizes[$i];
        }

        return $sum + $gap * max(0, $end - $start - 1);
    }

    /** Offset from the content origin to the start of a spanned cell. */
    private static function spanExtent(array $sizes, int $start, int $span, float $gap): float
    {
        $offset = 0.0;
        for ($i = 0; $i < $start; $i++) {
            $offset += $sizes[$i] + $gap;
        }

        return $offset;
    }

    /** Find the next free 1×1 cell for auto-placement (row-major). */
    private static function nextFreeCell(array &$occupied, int $cols, int $rows, int $from): array
    {
        $total = $cols * $rows;
        for ($i = $from; $i < $total; $i++) {
            if (! ($occupied[$i] ?? false)) {
                $occupied[$i] = true;

                return [$i % $cols, intdiv($i, $cols)];
            }
        }
        // Overflow: append to the end (beyond declared rows).
        return [$from % max(1, $cols), intdiv($from, max(1, $cols))];
    }
}
