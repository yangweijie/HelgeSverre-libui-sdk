<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\BreadcrumbItemRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\BreadcrumbItemSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PaginationItemRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PaginationItemSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Widgets\BreadcrumbControl;
use Yangweijie\Ui2\Widgets\PaginationControl;

$tokens = new DesignTokens();

test('BreadcrumbItemRenderer draws a hover wash when hovered, none when idle', function () use ($tokens): void {
    $idle = (new BreadcrumbItemRenderer())->shapeCommands(new BreadcrumbItemSpec(label: 'Home', isLast: false), $tokens, 80, 26);
    $hover = (new BreadcrumbItemRenderer())->shapeCommands(new BreadcrumbItemSpec(label: 'Home', hovered: true), $tokens, 80, 26);

    expect($idle)->toBe([]);
    expect(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(1);
});

test('BreadcrumbControl builds one crumb per item and marks the last active', function (): void {
    $bc = new BreadcrumbControl('path', [
        ['label' => 'Home'], ['label' => 'Library'], ['label' => 'Report'],
    ]);
    $root = $bc->root();
    expect($root->children)->toHaveCount(3);

    $last = $root->children[2]->spec;
    expect($last)->toBeInstanceOf(BreadcrumbItemSpec::class);
    expect($last->active)->toBeTrue();
    expect($root->children[0]->spec->active)->toBeFalse();
});

test('BreadcrumbControl navigate fires onNavigate with the crumb index', function (): void {
    $bc = new BreadcrumbControl('path', [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]);
    $hit = null;
    $bc->onNavigate(static function (int $i) use (&$hit): void {
        $hit = $i;
    });
    $bc->navigate(1);
    expect($hit)->toBe(1);
});

test('PaginationItemRenderer draws a filled chip when active, wash when hovered, nothing for gap', function () use ($tokens): void {
    $active = (new PaginationItemRenderer())->shapeCommands(new PaginationItemSpec(label: '5', active: true), $tokens, 32, 32);
    $hover = (new PaginationItemRenderer())->shapeCommands(new PaginationItemSpec(label: '4', hovered: true), $tokens, 32, 32);
    $gap = (new PaginationItemRenderer())->shapeCommands(new PaginationItemSpec(label: '…', kind: 'gap'), $tokens, 32, 32);

    expect(array_filter($active, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(1);
    expect(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(1);
    expect($gap)->toBe([]);
});

test('PaginationControl builds a windowed strip with gaps and disables prev at page 1', function (): void {
    $pager = new PaginationControl('p', totalPages: 20, active: 5);
    $root = $pager->root();

    // prev, 1, gap, 4, 5, 6, gap, 20, next  → 9 tokens
    expect($root->children)->toHaveCount(9);

    $kinds = array_map(static fn (LayoutNode $n) => $n->spec->kind, $root->children);
    expect($kinds)->toContain('gap');
    expect($kinds[0])->toBe('prev');
    expect($kinds[count($kinds) - 1])->toBe('next');

    // prev disabled on page 1
    $first = new PaginationControl('p2', totalPages: 20, active: 1);
    expect($first->root()->children[0]->spec->enabled)->toBeFalse();
});

test('PaginationControl goto changes the active page and rebuilds', function (): void {
    $pager = new PaginationControl('p', totalPages: 20, active: 5);
    $changed = null;
    $pager->onChange(static function (int $page) use (&$changed): void {
        $changed = $page;
    });
    $pager->goto(12);
    expect($pager->activePage())->toBe(12);
    expect($changed)->toBe(12);
    // active token should now be page 12
    $activeTok = null;
    foreach ($pager->root()->children as $tok) {
        if ($tok->spec->active) {
            $activeTok = $tok->spec;
        }
    }
    expect($activeTok)->not()->toBeNull();
    expect($activeTok->label)->toBe('12');
});

test('registry default registers the nav renderers', function (): void {
    $registry = RendererRegistry::default();
    expect($registry->get('breadcrumb_item'))->toBeInstanceOf(BreadcrumbItemRenderer::class);
    expect($registry->get('pagination_item'))->toBeInstanceOf(PaginationItemRenderer::class);
});

test('semantics mapType covers the nav widget types', function (): void {
    expect(SemanticsNode::mapType('breadcrumb_item')->name)->toBe('ListItem');
    expect(SemanticsNode::mapType('pagination_item')->name)->toBe('Button');
});
