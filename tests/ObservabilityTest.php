<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Observability\UiSnapshot;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Semantics\SemanticProvider;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * Headless tests for the observability / automation interfaces.
 *
 * These exercise the semantics-tree serialization and the UiSnapshot entry
 * point without booting libui (no FFI, no display), so they run in CI.
 */

final class StubProvider implements SemanticProvider
{
    public function __construct(private ?SemanticsNode $node)
    {
    }

    public function semantics(): ?SemanticsNode
    {
        return $this->node;
    }
}

test('SemanticsNode serializes to a plain array', function () {
    $node = SemanticsNode::fromLayout(
        LayoutNode::leaf('save', new ButtonSpec('Save'), width: 100, height: 36),
    );

    $array = $node->toArray();

    expect($array['id'])->toBe('save');
    expect($array['role'])->toBe('button');
    expect($array['label'])->toBe('Save');
    expect($array['enabled'])->toBeTrue();
    expect($array)->toHaveKey('rect');
    expect($array['rect'])->toHaveKeys(['x', 'y', 'w', 'h']);
    expect($array['children'])->toBe([]);
});

test('SemanticsNode serializes nested children recursively', function () {
    $root = LayoutNode::column()->child(
        LayoutNode::leaf('a', new ButtonSpec('A')),
    )->child(
        LayoutNode::leaf('b', new ButtonSpec('B')),
    );

    $array = SemanticsNode::fromLayout($root)->toArray();

    expect($array['children'])->toHaveCount(2);
    expect($array['children'][0]['id'])->toBe('a');
    expect($array['children'][1]['id'])->toBe('b');
});

test('SemanticsNode::fromControls aggregates SemanticProvider children', function () {
    $childA = new SemanticsNode('a', WidgetRole::Button);
    $childB = new SemanticsNode('b', WidgetRole::TextBox);

    $group = SemanticsNode::fromControls(
        [new StubProvider($childA), new StubProvider($childB)],
        WidgetRole::Group,
        'My Group',
    );

    expect($group)->not->toBeNull();
    expect($group->label)->toBe('My Group');
    expect($group->role)->toBe(WidgetRole::Group);
    expect($group->children)->toHaveCount(2);
    expect($group->children[0]->id)->toBe('a');
});

test('fromControls returns null when no child contributes a node', function () {
    $group = SemanticsNode::fromControls(
        [new StubProvider(null)],
        WidgetRole::Group,
    );

    expect($group)->toBeNull();
});

test('UiSnapshot::fromLayout builds the accessibility tree headlessly', function () {
    $root = LayoutNode::leaf('save', new ButtonSpec('Save'), width: 100, height: 36);
    FlexLayout::layout($root, 0, 0, 400, 300);

    $snapshot = UiSnapshot::fromLayout($root);
    $array = $snapshot->toArray();

    expect($array['role'])->toBe('button');
    expect($array['label'])->toBe('Save');
    // The laid-out root fills the 400×300 constraint (align stretch).
    expect($array['rect']['w'])->toBe(400.0);
    expect($array['rect']['h'])->toBe(300.0);

    $json = $snapshot->toJson();
    expect(json_decode($json, true))->not->toBeNull();
    expect(json_decode($json, true)['id'])->toBe('save');
});
