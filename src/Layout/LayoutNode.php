<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * A node in a declarative layout tree.
 *
 * A container node carries a {@see LayoutStyle} (direction/gap/align/…) and
 * children; a leaf node optionally carries a {@see WidgetSpec} describing the
 * self-drawn widget to paint at its computed rect. After
 * {@see FlexLayout::layout()} runs, every node has x/y/w/h filled in.
 *
 * The tree is deliberately dumb data — it holds style + children + spec and
 * the computed rect, nothing more. Measurement, distribution and painting all
 * live in dedicated collaborators so they can be tested in isolation.
 *
 * ```php
 * $root = LayoutNode::column(gap: 8, padding: 12)
 *     ->child(LayoutNode::leaf('header', $buttonSpec, height: 36))
 *     ->child(LayoutNode::row(gap: 8)->child(...)->child(...));
 * FlexLayout::layout($root, 0, 0, 400, 300);
 * ```
 */
class LayoutNode
{
    public LayoutStyle $style;

    /** @var list<LayoutNode> */
    public array $children = [];

    /** Leaf widget spec to render at this node's rect (null for pure containers). */
    public ?WidgetSpec $spec = null;

    /** Optional id for hit-testing / event routing / debugging. */
    public ?string $id = null;

    /** Computed geometry — written by {@see FlexLayout::layout()}. */
    public float $x = 0.0;
    public float $y = 0.0;
    public float $w = 0.0;
    public float $h = 0.0;

    /** Interaction state, mutated by a {@see \Yangweijie\Ui2\Widgets\Surface}. */
    public bool $pressed = false;
    public bool $hovered = false;

    /**
     * Scroll offset for a scroll container (a node whose leaf spec is a
     * ScrollViewSpec). Children are painted clipped to the node's rect and
     * translated by (-scrollX, -scrollY). Mutated by a
     * {@see \Yangweijie\Ui2\Widgets\ScrollViewControl} (or any owner).
     */
    public float $scrollX = 0.0;
    public float $scrollY = 0.0;

    /** Grid placement (used when the parent's {@see $grid} style is set). */
    public ?int $gridCol = null;
    public ?int $gridRow = null;
    public int $colSpan = 1;
    public int $rowSpan = 1;

    /** Grid style for this node when it is a grid container (null = use flex). */
    public ?GridStyle $grid = null;

    /**
     * Explicit ARIA-like role, overriding the one derived from the leaf spec's
     * type (see {@see \Yangweijie\Ui2\Semantics\SemanticsNode::mapType}). Set this
     * on containers that need a semantic role (e.g. a tab strip, a list).
     */
    public ?WidgetRole $role = null;

    public function __construct(?LayoutStyle $style = null, ?string $id = null)
    {
        $this->style = $style ?? new LayoutStyle();
        $this->id = $id;
    }

    /** Container convenience: a row (horizontal) with the given style fields. */
    public static function row(
        float $gap = 0.0,
        float $padding = 0.0,
        string $justify = LayoutStyle::JUSTIFY_START,
        string $align = LayoutStyle::ALIGN_STRETCH,
        ?string $id = null,
        ?float $width = null,
        ?float $height = null,
    ): self {
        return new self(new LayoutStyle(
            direction: LayoutStyle::ROW,
            gap: $gap,
            padding: $padding,
            justify: $justify,
            align: $align,
            width: $width,
            height: $height,
        ), $id);
    }

    /** Container convenience: a column (vertical) with the given style fields. */
    public static function column(
        float $gap = 0.0,
        float $padding = 0.0,
        string $justify = LayoutStyle::JUSTIFY_START,
        string $align = LayoutStyle::ALIGN_STRETCH,
        ?string $id = null,
        ?float $width = null,
        ?float $height = null,
    ): self {
        return new self(new LayoutStyle(
            direction: LayoutStyle::COLUMN,
            gap: $gap,
            padding: $padding,
            justify: $justify,
            align: $align,
            width: $width,
            height: $height,
        ), $id);
    }

    /**
     * Leaf convenience: a node that paints a widget spec at its rect.
     *
     * Pass width/height for a fixed-size leaf, or leave them null and set grow
     * on the returned node (via ->style) to make it fill available space.
     */
    public static function leaf(
        ?string $id,
        ?WidgetSpec $spec = null,
        ?float $width = null,
        ?float $height = null,
    ): self {
        $node = new self(new LayoutStyle(width: $width, height: $height), $id);
        $node->spec = $spec;

        return $node;
    }

    /** Append a child and return $this for chaining. */
    public function child(self $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /** Declare an explicit semantic role for this node (see {@see WidgetRole}). */
    public function withRole(WidgetRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    /** Build the accessibility/semantics tree rooted at this node. */
    public function semantics(): \Yangweijie\Ui2\Semantics\SemanticsNode
    {
        return \Yangweijie\Ui2\Semantics\SemanticsNode::fromLayout($this);
    }

    /** True when this node has children (a container) rather than being a leaf. */
    public function isContainer(): bool
    {
        return $this->children !== [];
    }

    /**
     * Find a node by id anywhere in the tree, or return null.
     *
     * Useful for event routing that needs a node's computed rect after layout.
     */
    public static function find(LayoutNode $root, string $id): ?self
    {
        if ($root->id === $id) {
            return $root;
        }
        foreach ($root->children as $child) {
            $found = self::find($child, $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
    /**
     * Topmost node id whose rect contains (x, y), or null. Children paint on
     * top of parents, so they are tested first (reverse order).
     *
     * Coordinates are in viewport (mouse) space; scroll containers are handled
     * automatically by converting the point into their content coordinate space.
     * Pure: needs only the computed geometry, so it is headless-testable
     * without constructing any libui control.
     */
    public static function findAt(LayoutNode $root, float $x, float $y): ?string
    {
        return self::findAtRecursive($root, $x, $y, 0.0, 0.0);
    }

    private static function findAtRecursive(LayoutNode $node, float $x, float $y, float $scrollX, float $scrollY): ?string
    {
        if ($node->spec instanceof ScrollViewSpec) {
            // Mouse coordinates are viewport-space; children live in content
            // space. Only recurse when the point is inside the visible viewport.
            $vpX = $node->x - $scrollX;
            $vpY = $node->y - $scrollY;
            if ($x >= $vpX && $x <= $vpX + $node->w && $y >= $vpY && $y <= $vpY + $node->h) {
                foreach (array_reverse($node->children) as $child) {
                    $hit = self::findAtRecursive($child, $x, $y, $scrollX + $node->scrollX, $scrollY + $node->scrollY);
                    if ($hit !== null) {
                        return $hit;
                    }
                }
            }
        } else {
            foreach (array_reverse($node->children) as $child) {
                $hit = self::findAtRecursive($child, $x, $y, $scrollX, $scrollY);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        $visibleX = $node->x - $scrollX;
        $visibleY = $node->y - $scrollY;
        if ($node->id !== null
            && $x >= $visibleX && $x <= $visibleX + $node->w
            && $y >= $visibleY && $y <= $visibleY + $node->h) {
            return $node->id;
        }

        return null;
    }

    /**
     * Collect the ids of every leaf widget (id set + a paintable spec) in
     * visit/paint order. These are the tab stops for the focus manager.
     *
     * @return list<string>
     */
    public static function focusables(LayoutNode $root): array
    {
        $out = [];
        if ($root->id !== null && $root->spec !== null) {
            $out[] = $root->id;
        }
        foreach ($root->children as $child) {
            $out = [...$out, ...self::focusables($child)];
        }

        return $out;
    }

    /**
     * Return the node path (root → … → target) for $id, or null when absent.
     * Used to compute a widget's on-screen rect by subtracting the scroll
     * offsets of every ancestor {@see ScrollViewSpec}.
     *
     * @return list<LayoutNode>|null
     */
    public static function pathTo(LayoutNode $root, string $id): ?array
    {
        if ($root->id === $id) {
            return [$root];
        }
        foreach ($root->children as $child) {
            $sub = self::pathTo($child, $id);
            if ($sub !== null) {
                return [$root, ...$sub];
            }
        }

        return null;
    }
}
