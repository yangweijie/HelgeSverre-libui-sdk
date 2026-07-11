<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;

// Helper: assert a node's computed rect as [x, y, w, h] with a small tolerance.
function expectRect(LayoutNode $n, array $expected): void
{
    expect([$n->x, $n->y, $n->w, $n->h])->toEqualWithDelta($expected, 0.001);
}

// ---------------------------------------------------------------------------
// Row layout: fixed-size children pack to the start
// ---------------------------------------------------------------------------

test('row packs fixed-size children to the start with no gap', function (): void {
    $root = LayoutNode::row()
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('b', null, width: 80.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($root->children[0], [0, 0, 100, 36]);
    expectRect($root->children[1], [100, 0, 80, 36]);
});

test('row respects gap between children', function (): void {
    $root = LayoutNode::row(gap: 10)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('b', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($root->children[0], [0, 0, 100, 36]);
    expectRect($root->children[1], [110, 0, 100, 36]);
});

test('absolute child is placed at (contentX + left, contentY + top), ignoring flow', function (): void {
    $abs = LayoutNode::leaf('pop', null, width: 120.0, height: 80.0);
    $abs->style->absolute = true;
    $abs->style->left = 30.0;
    $abs->style->top = 40.0;

    $root = LayoutNode::column(padding: 10, width: 300.0, height: 200.0)
        ->child(LayoutNode::leaf('flow', null, width: 100.0, height: 20.0))
        ->child($abs);

    FlexLayout::layout($root, 0, 0, 300, 200);

    // content origin is (10, 10) due to padding; absolute child lands there + offset.
    expectRect($abs, [40, 50, 120, 80]);
    // flow child is unaffected by the absolute sibling
    expectRect($root->children[0], [10, 10, 100, 20]);
});

test('row respects padding', function (): void {
    $root = LayoutNode::row(padding: 8)
        ->child(LayoutNode::leaf('a', null, width: 100.0)); // no height → stretches cross

    FlexLayout::layout($root, 0, 0, 200, 60);

    expectRect($root->children[0], [8, 8, 100, 44]); // cross stretched to 60-2*8
});

// ---------------------------------------------------------------------------
// grow: leftover main-axis space distributed proportionally
// ---------------------------------------------------------------------------

test('grow distributes leftover space proportionally', function (): void {
    $a = LayoutNode::leaf('a', null, width: 100.0, height: 36.0);
    $b = LayoutNode::leaf('b', null, height: 36.0);
    $b->style->grow = 1.0;
    $c = LayoutNode::leaf('c', null, height: 36.0);
    $c->style->grow = 2.0;

    $root = LayoutNode::row()->child($a)->child($b)->child($c);
    FlexLayout::layout($root, 0, 0, 400, 36);

    // leftover = 400 - 100 = 300; b gets 100, c gets 200
    expectRect($a, [0, 0, 100, 36]);
    expectRect($b, [100, 0, 100, 36]);
    expectRect($c, [200, 0, 200, 36]);
});

test('grow of zero leaves fixed children at their size', function (): void {
    $a = LayoutNode::leaf('a', null, width: 100.0, height: 36.0);
    $b = LayoutNode::leaf('b', null, width: 50.0, height: 36.0);

    $root = LayoutNode::row()->child($a)->child($b);
    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($a, [0, 0, 100, 36]);
    expectRect($b, [100, 0, 50, 36]);
});

// ---------------------------------------------------------------------------
// justify: distribution when no child grows
// ---------------------------------------------------------------------------

test('justify center centers packed children', function (): void {
    $root = LayoutNode::row(justify: LayoutStyle::JUSTIFY_CENTER)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('b', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($root->children[0], [100, 0, 100, 36]); // (400-200)/2
    expectRect($root->children[1], [200, 0, 100, 36]);
});

test('justify end packs children to the right', function (): void {
    $root = LayoutNode::row(justify: LayoutStyle::JUSTIFY_END)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($root->children[0], [300, 0, 100, 36]);
});

test('justify spaceBetween puts no leading space and splits gaps', function (): void {
    $root = LayoutNode::row(justify: LayoutStyle::JUSTIFY_SPACE_BETWEEN)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('b', null, width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('c', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 36);

    expectRect($root->children[0], [0, 0, 100, 36]);
    expectRect($root->children[1], [150, 0, 100, 36]); // 100 + 50 gap
    expectRect($root->children[2], [300, 0, 100, 36]);
});

// ---------------------------------------------------------------------------
// align: cross-axis placement
// ---------------------------------------------------------------------------

test('align stretch fills cross axis when no fixed cross size', function (): void {
    $a = LayoutNode::leaf('a', null, width: 100.0); // no height

    $root = LayoutNode::row(align: LayoutStyle::ALIGN_STRETCH)->child($a);
    FlexLayout::layout($root, 0, 0, 400, 60);

    expectRect($a, [0, 0, 100, 60]); // stretched to full height
});

test('align center centers a fixed-height child vertically', function (): void {
    $root = LayoutNode::row(align: LayoutStyle::ALIGN_CENTER)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 60);

    expectRect($root->children[0], [0, 12, 100, 36]); // (60-36)/2
});

test('align end places a fixed-height child at the bottom', function (): void {
    $root = LayoutNode::row(align: LayoutStyle::ALIGN_END)
        ->child(LayoutNode::leaf('a', null, width: 100.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 400, 60);

    expectRect($root->children[0], [0, 24, 100, 36]); // 60-36
});

// ---------------------------------------------------------------------------
// Column direction
// ---------------------------------------------------------------------------

test('column stacks children vertically with gap', function (): void {
    $root = LayoutNode::column(gap: 8)
        ->child(LayoutNode::leaf('a', null, width: 120.0, height: 36.0))
        ->child(LayoutNode::leaf('b', null, width: 120.0, height: 36.0));

    FlexLayout::layout($root, 0, 0, 120, 200);

    expectRect($root->children[0], [0, 0, 120, 36]);
    expectRect($root->children[1], [0, 44, 120, 36]);
});

test('column grow distributes vertical leftover', function (): void {
    $a = LayoutNode::leaf('a', null, height: 40.0, width: 100.0);
    $b = LayoutNode::leaf('b', null, width: 100.0);
    $b->style->grow = 1.0;

    $root = LayoutNode::column()->child($a)->child($b);
    FlexLayout::layout($root, 0, 0, 100, 200);

    expectRect($a, [0, 0, 100, 40]);
    expectRect($b, [0, 40, 100, 160]); // 200 - 40
});

// ---------------------------------------------------------------------------
// Nesting / recursion
// ---------------------------------------------------------------------------

test('nested row inside column gets its own sub-rect', function (): void {
    $inner = LayoutNode::row(gap: 4)
        ->child(LayoutNode::leaf('i1', null, width: 30.0, height: 20.0))
        ->child(LayoutNode::leaf('i2', null, width: 30.0, height: 20.0));

    $root = LayoutNode::column(gap: 8, padding: 4)
        ->child(LayoutNode::leaf('top', null, height: 30.0)) // no width → stretches cross
        ->child($inner);

    FlexLayout::layout($root, 0, 0, 200, 100);

    // top: x=4, y=4, w=192 (cross-stretched), h=30 (main fixed)
    expectRect($root->children[0], [4, 4, 192, 30]);
    // inner container: x=4, y=4+30+8(gap)=42, w=192 (cross-stretched), h=0 (main, no grow)
    expectRect($inner, [4, 42, 192, 0]);
    // inner's children: fixed 30×20, anchored at inner's origin
    expectRect($inner->children[0], [4, 42, 30, 20]);
    expectRect($inner->children[1], [38, 42, 30, 20]);
});

// ---------------------------------------------------------------------------
// Overflow: shrink reclaims space when children exceed the container
// ---------------------------------------------------------------------------

test('shrink reclaims space proportionally on overflow', function (): void {
    $a = LayoutNode::leaf('a', null, width: 300.0, height: 36.0);
    $a->style->shrink = 1.0;
    $b = LayoutNode::leaf('b', null, width: 300.0, height: 36.0);
    $b->style->shrink = 1.0;

    $root = LayoutNode::row()->child($a)->child($b);
    FlexLayout::layout($root, 0, 0, 400, 36);

    // total 600 in 400 → deficit 200; equal shrink weights + equal bases →
    // each loses 100 → both end up 200 wide.
    expectRect($a, [0, 0, 200, 36]);
    expectRect($b, [200, 0, 200, 36]);
});

// ---------------------------------------------------------------------------
// Root geometry is set to the containing rect
// ---------------------------------------------------------------------------

test('layout sets the root rect to the containing rect', function (): void {
    $root = LayoutNode::row();
    FlexLayout::layout($root, 10, 20, 300, 40);

    expectRect($root, [10, 20, 300, 40]);
});

// ---------------------------------------------------------------------------
// Tree navigation helpers
// ---------------------------------------------------------------------------

test('LayoutNode::find returns the matching node or null', function (): void {
    $leaf = LayoutNode::leaf('target', null, width: 50.0, height: 20.0);
    $root = LayoutNode::column()
        ->child(LayoutNode::row()->child(LayoutNode::leaf('a', null, width: 10.0, height: 10.0)))
        ->child($leaf)
        ->child(LayoutNode::leaf('c', null, width: 10.0, height: 10.0));

    expect(LayoutNode::find($root, 'target'))->toBe($leaf);
    expect(LayoutNode::find($root, 'missing'))->toBeNull();
});
