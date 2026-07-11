<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SearchFieldRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SearchFieldSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Widgets\ComboboxControl;
use Yangweijie\Ui2\Widgets\DropdownMenuControl;
use Yangweijie\Ui2\Widgets\SearchFieldControl;
use Yangweijie\Ui2\Widgets\Surface;

$tokens = new DesignTokens();

test('SearchFieldRenderer draws a field box plus a wash when hovered', function () use ($tokens): void {
    $idle = (new SearchFieldRenderer())->shapeCommands(new SearchFieldSpec(value: 'ab'), $tokens, 220, 34);
    $hover = (new SearchFieldRenderer())->shapeCommands(new SearchFieldSpec(value: 'ab', hovered: true), $tokens, 220, 34);

    expect(array_filter($idle, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(1);
    expect(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(2);
});

test('SearchFieldControl builds a field + clear row and clears on setValue', function (): void {
    $s = new SearchFieldControl('q', value: 'hello');
    $root = $s->root();
    expect($root->children)->toHaveCount(2);
    expect($s->value())->toBe('hello');

    $s->setValue('');
    expect($s->value())->toBe('');
    // clear button disabled when empty
    expect($root->children[1]->spec->enabled)->toBeFalse();
});

test('DropdownMenuControl opens an OVERLAY panel below the trigger (no layout push)', function (): void {
    $m = new DropdownMenuControl('sort', ['Name', 'Size', 'Date'], selected: 0, width: 200);
    $m->root()->style->width = 200.0;
    $m->root()->style->height = 34.0;
    $root = LayoutNode::column()->child($m->root());
    $surface = new Surface($root);
    $m->bind($surface);

    FlexLayout::layout($root, 0, 0, 800, 600);

    expect($m->root()->children)->toHaveCount(1); // closed: trigger only, no inline panel
    expect($m->isOpen())->toBeFalse();

    $m->open();
    expect($m->isOpen())->toBeTrue();

    // The panel lives in a Surface overlay, NOT inside the control's own tree.
    $overlay = $surface->overlay();
    expect($overlay)->not->toBeNull();
    expect($m->root()->children)->toHaveCount(1); // still just the trigger

    $panel = LayoutNode::find($overlay, 'sort:panel');
    expect($panel)->not->toBeNull();
    expect($panel->style->absolute)->toBeTrue();

    // The panel is anchored BELOW the trigger (not overlapping / pushing it).
    $trigger = LayoutNode::find($surface->rootLayout(), 'sort');
    expect($panel->style->top)->toBeGreaterThanOrEqual($trigger->y + $trigger->h - 0.001);
    expect($panel->style->left)->toBe($trigger->x);

    // Selecting dismisses the overlay and reports the choice.
    $hit = null;
    $m->onSelect(static function (int $i, string $label) use (&$hit): void {
        $hit = [$i, $label];
    });
    $m->select(2);
    expect($hit)->toBe([2, 'Date']);
    expect($m->selectedIndex())->toBe(2);
    expect($m->isOpen())->toBeFalse();
    expect($surface->overlay())->toBeNull();
});

test('ComboboxControl opens an overlay panel below the field and picks into it', function (): void {
    $c = new ComboboxControl('lang', ['PHP', 'Rust', 'Go'], value: 'PHP', width: 220);
    $c->root()->style->width = 220.0;
    $c->root()->style->height = 34.0;
    $root = LayoutNode::column()->child($c->root());
    $surface = new Surface($root);
    $c->bind($surface);
    FlexLayout::layout($root, 0, 0, 800, 600);

    expect($c->value())->toBe('PHP');
    expect($c->root()->children)->toHaveCount(1); // closed: bar only

    $c->open();
    expect($c->isOpen())->toBeTrue();
    expect($surface->overlay())->not->toBeNull();
    expect($c->root()->children)->toHaveCount(1); // no inline panel pushed into the tree

    $c->pick(1);
    expect($c->value())->toBe('Rust');
    expect($c->root()->children)->toHaveCount(1);
    expect($surface->overlay())->toBeNull();

    // typing replaces the value
    $c->setValue('Python');
    expect($c->value())->toBe('Python');
});

test('registry default registers the search renderer', function (): void {
    $registry = RendererRegistry::default();
    expect($registry->get('search_field'))->toBeInstanceOf(SearchFieldRenderer::class);
});

test('semantics mapType covers search_field', function (): void {
    expect(SemanticsNode::mapType('search_field')->name)->toBe('TextBox');
});
