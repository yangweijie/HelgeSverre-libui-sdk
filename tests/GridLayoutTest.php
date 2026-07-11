<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\GridLayout;
use Yangweijie\Ui2\Layout\GridStyle;
use Yangweijie\Ui2\Layout\GridTrack;
use Yangweijie\Ui2\Layout\LayoutNode;

function expectGridRect(LayoutNode $n, array $expected): void
{
    expect([$n->x, $n->y, $n->w, $n->h])->toEqualWithDelta($expected, 0.001);
}

test('grid distributes fr columns equally', function (): void {
    $root = (new LayoutNode())
        ->child(LayoutNode::leaf('a'))
        ->child(LayoutNode::leaf('b'));
    $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], [GridTrack::fr(1)]);

    GridLayout::layout($root, 0, 0, 200, 100);

    expectGridRect($root->children[0], [0, 0, 100, 100]);
    expectGridRect($root->children[1], [100, 0, 100, 100]);
});

test('grid respects px and fr mix', function (): void {
    $root = (new LayoutNode())
        ->child(LayoutNode::leaf('a'))
        ->child(LayoutNode::leaf('b'))
        ->child(LayoutNode::leaf('c'));
    // 80px + 1fr + 2fr in 400px → 80 + 320 split 1:2 = 80 + ~107 + ~213
    $root->grid = new GridStyle(
        [GridTrack::px(80), GridTrack::fr(1), GridTrack::fr(2)],
        [GridTrack::fr(1)],
    );

    GridLayout::layout($root, 0, 0, 400, 100);

    expectGridRect($root->children[0], [0, 0, 80, 100]);
    expectGridRect($root->children[1], [80, 0, 106.667, 100]);
    expectGridRect($root->children[2], [186.667, 0, 213.333, 100]);
});

test('grid respects gap', function (): void {
    $root = (new LayoutNode())
        ->child(LayoutNode::leaf('a'))
        ->child(LayoutNode::leaf('b'));
    $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], [GridTrack::fr(1)], gap: 10);

    GridLayout::layout($root, 0, 0, 210, 100);

    expectGridRect($root->children[0], [0, 0, 100, 100]);
    expectGridRect($root->children[1], [110, 0, 100, 100]);
});

test('grid explicit placement with colspan', function (): void {
    $a = LayoutNode::leaf('a');
    $a->gridCol = 0;
    $a->gridRow = 0;
    $a->colSpan = 2; // spans both columns

    $root = (new LayoutNode())->child($a);
    $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], [GridTrack::fr(1)]);

    GridLayout::layout($root, 0, 0, 200, 100);

    expectGridRect($a, [0, 0, 200, 100]); // spans full width
});

test('grid auto-flows row-major across rows', function (): void {
    $root = (new LayoutNode());
    for ($i = 0; $i < 4; $i++) {
        $root->child(LayoutNode::leaf('c' . $i));
    }
    $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], [GridTrack::fr(1), GridTrack::fr(1)]);

    GridLayout::layout($root, 0, 0, 200, 100);

    expectGridRect($root->children[0], [0, 0, 100, 50]);     // row 0 col 0
    expectGridRect($root->children[1], [100, 0, 100, 50]);   // row 0 col 1
    expectGridRect($root->children[2], [0, 50, 100, 50]);    // row 1 col 0
    expectGridRect($root->children[3], [100, 50, 100, 50]);  // row 1 col 1
});

test('grid padding offsets children', function (): void {
    $root = (new LayoutNode())->child(LayoutNode::leaf('a'));
    $root->grid = new GridStyle([GridTrack::fr(1)], [GridTrack::fr(1)], padding: 10);

    GridLayout::layout($root, 0, 0, 100, 100);

    expectGridRect($root->children[0], [10, 10, 80, 80]);
});

test('grid with no rows auto-creates equal-height rows', function (): void {
    $root = (new LayoutNode());
    for ($i = 0; $i < 3; $i++) {
        $root->child(LayoutNode::leaf('c' . $i));
    }
    // 2 columns, 3 children → 2 rows (ceil(3/2)=2)
    $root->grid = new GridStyle([GridTrack::fr(1), GridTrack::fr(1)], []);

    GridLayout::layout($root, 0, 0, 200, 100);

    expectGridRect($root->children[0], [0, 0, 100, 50]);
    expectGridRect($root->children[1], [100, 0, 100, 50]);
    expectGridRect($root->children[2], [0, 50, 100, 50]);
});
