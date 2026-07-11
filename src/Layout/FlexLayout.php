<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

/**
 * A flexbox layout engine — computes pixel rects for a {@see LayoutNode} tree.
 *
 * This is the PHP/libui counterpart of the native SDK's `layoutAxisChildren`:
 * one pass per container distributes main-axis space (grow/shrink/justify),
 * aligns children on the cross axis, then recurses. It is pure — given a tree
 * and a containing rect it fills in every node's x/y/w/h with no libui calls,
 * so it is fully testable headlessly.
 *
 * Why this exists: libui's own containers (Box/Group) give Areas no intrinsic
 * size and have no flex/grid model, which made self-drawn widgets collapse or
 * overflow (see the RendererButton saga). By running our own layout over a
 * single canvas Area we get real flexbox semantics — grow, gap, justify, align,
 * nested rows/columns — independent of libui's layout quirks.
 *
 * Usage:
 *   $root = LayoutNode::row(gap: 8)
 *       ->child(LayoutNode::leaf('a', null, width: 100))
 *       ->child(LayoutNode::leaf('b', null)->style->grow = 1);
 *   FlexLayout::layout($root, 0, 0, 400, 36);
 *   // $a = [0,0,100,36], $b = [108,0,292,36]
 */
final class FlexLayout
{
    /**
     * Lay out $root inside the rect (x, y, w, h), filling every node's
     * computed geometry. The root's own x/y/w/h are set to the given rect.
     */
    public static function layout(LayoutNode $root, float $x, float $y, float $w, float $h): void
    {
        $root->x = $x;
        $root->y = $y;
        $root->w = $w;
        $root->h = $h;

        self::layoutChildren($root);
    }

    /**
     * Distribute this container's children inside its computed rect, then
     * recurse. Container geometry (x/y/w/h) must already be set on $node.
     * Public so {@see GridLayout} can recurse into flex sub-containers.
     */
    public static function layoutChildren(LayoutNode $node): void
    {
        $children = $node->children;
        $n = count($children);
        if ($n === 0) {
            return;
        }

        $s = $node->style;
        $pad = $s->padding;
        $contentX = $node->x + $pad;
        $contentY = $node->y + $pad;
        $contentW = $node->w - 2 * $pad;
        $contentH = $node->h - 2 * $pad;

        $isRow = ! $s->isColumn();
        // Main axis = width for row, height for column; cross axis = the other.
        $mainSize = $isRow ? $contentW : $contentH;
        $crossSize = $isRow ? $contentH : $contentW;

        // Partition children into flow (flex-distributed) and absolute
        // (explicitly positioned) so absolute overlays don't disturb siblings.
        $flow = [];
        $absolute = [];
        foreach ($children as $c) {
            if ($c->style->absolute) {
                $absolute[] = $c;
            } else {
                $flow[] = $c;
            }
        }

        // 1. Flex-basis: each flow child's starting main-axis size.
        //    Fixed main size > basis > 0 (no content measurement in v1).
        $bases = [];
        $totalBase = 0.0;
        foreach ($flow as $i => $c) {
            $cs = $c->style;
            $fixed = $isRow ? $cs->width : $cs->height;
            $base = $fixed ?? $cs->basis ?? 0.0;
            $bases[$i] = $base;
            $totalBase += $base;
        }

        $fn = count($flow);
        $totalGap = $s->gap * max(0, $fn - 1);
        $free = $mainSize - $totalBase - $totalGap;

        // 2. Grow or shrink to absorb free / deficit space.
        $sizes = $bases;
        if ($free > 0) {
            $totalGrow = 0.0;
            foreach ($flow as $c) {
                $totalGrow += max(0.0, $c->style->grow);
            }
            if ($totalGrow > 0) {
                foreach ($flow as $i => $c) {
                    $g = max(0.0, $c->style->grow);
                    if ($g > 0) {
                        $sizes[$i] += $free * ($g / $totalGrow);
                    }
                }
                $free = 0.0; // grow consumed all leftover
            }
        } elseif ($free < 0) {
            // Shrink proportionally to shrinkFactor * basis (CSS-like).
            $totalShrinkWeight = 0.0;
            foreach ($flow as $i => $c) {
                $weight = max(0.0, $c->style->shrink) * $bases[$i];
                $totalShrinkWeight += $weight;
            }
            if ($totalShrinkWeight > 0) {
                foreach ($flow as $i => $c) {
                    $weight = max(0.0, $c->style->shrink) * $bases[$i];
                    $sizes[$i] += $free * ($weight / $totalShrinkWeight);
                }
            }
            $free = 0.0;
        }

        // 3. Justify: distribute any remaining (positive) free space along main.
        $leadingOffset = 0.0;
        $betweenGap = $s->gap;
        if ($free > 0) {
            switch ($s->justify) {
                case LayoutStyle::JUSTIFY_START:
                    $leadingOffset = 0.0;
                    break;
                case LayoutStyle::JUSTIFY_CENTER:
                    $leadingOffset = $free / 2.0;
                    break;
                case LayoutStyle::JUSTIFY_END:
                    $leadingOffset = $free;
                    break;
                case LayoutStyle::JUSTIFY_SPACE_BETWEEN:
                    $betweenGap = $s->gap + ($fn > 1 ? $free / ($fn - 1) : 0.0);
                    break;
                case LayoutStyle::JUSTIFY_SPACE_AROUND:
                    $slot = $free / $fn;
                    $leadingOffset = $slot / 2.0;
                    $betweenGap = $s->gap + $slot;
                    break;
                case LayoutStyle::JUSTIFY_SPACE_EVENLY:
                    $slot = $free / ($fn + 1);
                    $leadingOffset = $slot;
                    $betweenGap = $s->gap + $slot;
                    break;
            }
        }

        // 4. Position flow children along the main axis and resolve cross size.
        $cursor = ($isRow ? $contentX : $contentY) + $leadingOffset;
        foreach ($flow as $i => $c) {
            $size = $sizes[$i];
            $cs = $c->style;
            $crossFixed = $isRow ? $cs->height : $cs->width;
            $childCross = $crossFixed ?? ($s->align === LayoutStyle::ALIGN_STRETCH ? $crossSize : 0.0);

            if ($isRow) {
                $c->x = $cursor;
                $c->w = $size;
                [$c->y, $c->h] = self::crossAxis($contentY, $crossSize, $childCross, $s->align);
            } else {
                $c->y = $cursor;
                $c->h = $size;
                [$c->x, $c->w] = self::crossAxis($contentX, $crossSize, $childCross, $s->align);
            }

            $cursor += $size + $betweenGap;
        }

        // 4b. Absolute children: place at (contentX + left, contentY + top) with
        // their own explicit size; they never affect the flow above.
        foreach ($absolute as $c) {
            $c->x = $contentX + $c->style->left;
            $c->y = $contentY + $c->style->top;
            if ($c->style->width !== null) {
                $c->w = $c->style->width;
            }
            if ($c->style->height !== null) {
                $c->h = $c->style->height;
            }
        }

        // 5. Recurse into each child container.
        foreach ($children as $c) {
            if ($c->isContainer()) {
                self::layoutChildren($c);
            }
        }
    }

    /**
     * Resolve a child's cross-axis origin and size given the container's cross
     * content origin/size, the child's fixed cross size (if any), and align.
     *
     * @return array{0:float,1:float} [origin, size]
     */
    private static function crossAxis(float $origin, float $container, float $child, string $align): array
    {
        // $child is already resolved: a fixed cross size wins; otherwise (under
        // stretch) it has been set to $container, and (under other aligns) to 0.
        return match ($align) {
            LayoutStyle::ALIGN_START => [$origin, $child],
            LayoutStyle::ALIGN_CENTER => [$origin + ($container - $child) / 2.0, $child],
            LayoutStyle::ALIGN_END => [$origin + ($container - $child), $child],
            // stretch: use the resolved child size (container when no fixed size).
            default => [$origin, $child],
        };
    }
}
