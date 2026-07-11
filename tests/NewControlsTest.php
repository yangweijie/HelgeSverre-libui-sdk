<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogBodyRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogBodySpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogCardRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DialogCardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TabRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TabSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TableRowRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TableRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\Widgets\DialogControl;
use Yangweijie\Ui2\Widgets\ListControl;
use Yangweijie\Ui2\Widgets\Surface;
use Yangweijie\Ui2\Widgets\TabControl;
use Yangweijie\Ui2\Widgets\TableControl;

$tokens = new DesignTokens();

/*
 * ─────────────────────────────────────────────────────────────────────────
 * Phase 7 wash parity: every Phase 7 renderer consumes the token-driven hover
 * / disabled wash exactly like ButtonRenderer (the only one wired in before).
 * ─────────────────────────────────────────────────────────────────────────
 */

test('checkbox renderer draws a hover wash when hovered, disabled wash when disabled', function () use ($tokens): void {
    $idle = (new CheckboxRenderer())->shapeCommands(new CheckboxSpec(checked: true, hovered: false), $tokens, 160, 24);
    $hover = (new CheckboxRenderer())->shapeCommands(new CheckboxSpec(checked: true, hovered: true), $tokens, 160, 24);
    $disabled = (new CheckboxRenderer())->shapeCommands(new CheckboxSpec(checked: true, enabled: false), $tokens, 160, 24);

    $idleFills = array_values(array_filter($idle, fn ($c) => $c instanceof FillRoundedRect));
    $hoverFills = array_values(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect));
    $disabledFills = array_values(array_filter($disabled, fn ($c) => $c instanceof FillRoundedRect));

    // hovered adds exactly one wash overlay on top of the idle fills
    expect(count($hoverFills))->toBe(count($idleFills) + 1);
    expect($hoverFills[array_key_last($hoverFills)]->color)->toEqual($tokens->hoverWash());

    // disabled adds a disabled wash overlay
    expect($disabledFills[array_key_last($disabledFills)]->color)->toEqual($tokens->disabledWash());
});

test('slider draw a hover wash when hovered', function () use ($tokens): void {
    $idle = (new SliderRenderer())->shapeCommands(new SliderSpec(value: 0.5), $tokens, 200, 24);
    $hover = (new SliderRenderer())->shapeCommands(new SliderSpec(value: 0.5, hovered: true), $tokens, 200, 24);

    $idleFills = array_values(array_filter($idle, fn ($c) => $c instanceof FillRoundedRect));
    $hoverFills = array_values(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect));

    expect(count($hoverFills))->toBe(count($idleFills) + 1);
    expect($hoverFills[array_key_last($hoverFills)]->color)->toEqual($tokens->hoverWash());
});

test('radio / select / textfield / progress / card all draw a token wash when hovered', function () use ($tokens): void {
    $cases = [
        'radio' => [(new RadioRenderer()), new RadioSpec(selected: true, hovered: true)],
        'select' => [(new SelectRenderer()), new SelectSpec(value: 'X', hovered: true)],
        'textfield' => [(new TextFieldRenderer()), new TextFieldSpec(value: 'hi', hovered: true)],
        'progress' => [(new ProgressRenderer()), new ProgressSpec(value: 0.5, hovered: true)],
        'card' => [(new \Yangweijie\Ui2\Rendering\WidgetRenderer\CardRenderer()), new CardSpec(hovered: true)],
    ];

    foreach ($cases as [$renderer, $spec]) {
        $cmds = $renderer->shapeCommands($spec, $tokens, 200, 30);
        $fills = array_values(array_filter($cmds, fn ($c) => $c instanceof FillRoundedRect));
        // at least one fill equals the hover wash
        $hasWash = false;
        foreach ($fills as $f) {
            if ($f->color->r === $tokens->hoverWash()->r
                && $f->color->g === $tokens->hoverWash()->g
                && $f->color->b === $tokens->hoverWash()->b
                && $f->color->a === $tokens->hoverWash()->a) {
                $hasWash = true;
            }
        }
        expect($hasWash)->toBeTrue("{$spec->type()} should draw a hover wash");
    }
});

/*
 * ─────────────────────────────────────────────────────────────────────────
 * New composite controls: List, Table, Tabs, Dialog.
 * ─────────────────────────────────────────────────────────────────────────
 */

test('ListControl builds selectable rows and switches selection', function (): void {
    $list = new ListControl('l', [
        ['id' => 'a', 'label' => 'Apple', 'subtitle' => 'Red'],
        ['id' => 'b', 'label' => 'Banana', 'subtitle' => 'Yellow'],
    ], selected: 0);

    expect($list->root()->role)->toBe(WidgetRole::List);
    expect($list->root()->children)->toHaveCount(2);
    expect($list->root()->children[0]->id)->toBe('l:row:0');
    expect($list->root()->children[0]->spec->selected)->toBeTrue();
    expect($list->root()->children[1]->spec->selected)->toBeFalse();

    $list->select(1);
    expect($list->selectedIndex())->toBe(1);
    expect($list->root()->children[1]->spec->selected)->toBeTrue();
    expect($list->root()->children[0]->spec->selected)->toBeFalse();
});

test('ListRowRenderer draws a selection fill and a bottom hairline', function () use ($tokens): void {
    $cmds = (new ListRowRenderer())->shapeCommands(new ListRowSpec(label: 'A', selected: true), $tokens, 200, 44);

    $fills = array_values(array_filter($cmds, fn ($c) => $c instanceof FillRoundedRect));
    // selection tint fill
    expect($fills[0]->color)->toEqual($tokens->selection());

    // non-selected rows get a hairline separator, not the selection fill
    $plain = (new ListRowRenderer())->shapeCommands(new ListRowSpec(label: 'A'), $tokens, 200, 44);
    $lines = array_values(array_filter($plain, fn ($c) => $c instanceof StrokeLine));
    expect($lines)->toHaveCount(1);
});

test('TableControl builds a header + data rows with shared columns', function (): void {
    $table = new TableControl('t',
        columns: [['label' => 'Name', 'width' => 2], ['label' => 'Role', 'width' => 1]],
        rows: [['cells' => ['Ada', 'Admin']], ['cells' => ['Linus', 'User']]],
        selected: 0,
    );

    $root = $table->root();
    expect($root->children)->toHaveCount(3); // header + 2 rows
    expect($root->children[0]->spec)->toBeInstanceOf(TableRowSpec::class);
    expect($root->children[0]->spec->header)->toBeTrue();
    expect($root->children[1]->spec->cells)->toBe(['Ada', 'Admin']);
    expect($root->children[1]->spec->selected)->toBeTrue();

    $table->select(1);
    expect($table->selectedIndex())->toBe(1);
    expect($root->children[2]->spec->selected)->toBeTrue();
});

test('TableRowRenderer honours column widths when laying out cells', function () use ($tokens): void {
    $cmds = (new TableRowRenderer())->shapeCommands(
        new TableRowSpec(cells: ['Ada', 'Admin'], widths: [2.0, 1.0], header: true),
        $tokens, 300, 36,
    );
    // header draws a bottom rule
    $lines = array_values(array_filter($cmds, fn ($c) => $c instanceof StrokeLine));
    expect($lines)->toHaveCount(1);
});

test('TabControl builds a strip + swappable panel and switches active tab', function (): void {
    $tabs = new TabControl('t', [
        ['id' => 'home', 'label' => 'Home', 'content' => LayoutNode::leaf('home', new CardSpec())],
        ['id' => 'about', 'label' => 'About', 'content' => LayoutNode::leaf('about', new CardSpec())],
    ]);

    $root = $tabs->root();
    expect($root->children)->toHaveCount(2); // bar + panel slot
    expect($root->children[0]->role)->toBe(WidgetRole::TabList);
    expect($root->children[0]->children)->toHaveCount(2);
    expect($root->children[1]->children)->toHaveCount(1); // one active panel
    expect($root->children[1]->children[0]->id)->toBe('home');
    expect($tabs->activeIndex())->toBe(0);

    $tabs->setActive(1);
    expect($tabs->activeIndex())->toBe(1);
    expect($root->children[1]->children[0]->id)->toBe('about');
    expect($root->children[0]->children[1]->spec->active)->toBeTrue();
    expect($root->children[0]->children[0]->spec->active)->toBeFalse();
});

test('TabRenderer draws an active underline indicator', function () use ($tokens): void {
    $active = (new TabRenderer())->shapeCommands(new TabSpec(label: 'Home', active: true), $tokens, 110, 38);
    $idle = (new TabRenderer())->shapeCommands(new TabSpec(label: 'Home', active: false), $tokens, 110, 38);

    $activeLines = array_values(array_filter($active, fn ($c) => $c instanceof StrokeLine));
    $idleLines = array_values(array_filter($idle, fn ($c) => $c instanceof StrokeLine));
    expect($activeLines)->toHaveCount(1); // underline
    expect($idleLines)->toHaveCount(0);
});

test('DialogControl builds a centered card with title/body and action buttons', function (): void {
    $dialog = new DialogControl('dlg', 'Delete?', 'This cannot be undone.', [
        ['id' => 'cancel', 'label' => 'Cancel', 'variant' => 'outline'],
        ['id' => 'ok', 'label' => 'Delete', 'variant' => 'filled'],
    ]);

    $overlay = $dialog->overlay();
    expect($overlay->role)->toBe(WidgetRole::Dialog);
    $card = $overlay->children[0];
    expect($card->spec)->toBeInstanceOf(DialogCardSpec::class);
    expect($card->children[0]->spec)->toBeInstanceOf(DialogBodySpec::class);
    $btnRow = $card->children[1];
    expect($btnRow->children)->toHaveCount(2);
    expect($btnRow->children[0]->id)->toBe('dlg:cancel');
    expect($btnRow->children[1]->id)->toBe('dlg:ok');
});

test('DialogCardRenderer draws a surface fill + border; DialogBodyRenderer draws text', function () use ($tokens): void {
    $card = (new DialogCardRenderer())->shapeCommands(new DialogCardSpec(), $tokens, 360, 216);
    expect($card)->toHaveCount(2);
    expect($card[0])->toBeInstanceOf(FillRoundedRect::class);

    $body = (new DialogBodyRenderer())->render(new DialogBodySpec(title: 'Hi', message: 'There'), $tokens, 320, 130);
    expect($body)->toBeInstanceOf(\Yangweijie\Ui2\Rendering\RenderCommandList::class);
});

test('Surface overlay captures focus and events for the modal tree', function (): void {
    $surface = new Surface(LayoutNode::leaf('x', new ButtonSpec('X')));
    expect($surface->overlay())->toBeNull();
    expect($surface->focus()->tabOrder())->toContain('x');

    $overlay = LayoutNode::leaf('ov', new ButtonSpec('OV'));
    $surface->setOverlay($overlay);
    expect($surface->overlay())->not->toBeNull();
    expect($surface->focus()->tabOrder())->toContain('ov');
    expect($surface->focus()->tabOrder())->not->toContain('x');

    $surface->setOverlay(null);
    expect($surface->overlay())->toBeNull();
    expect($surface->focus()->tabOrder())->toContain('x');
});

test('DialogControl open/close installs and removes the overlay, reporting the button', function (): void {
    $surface = new Surface(LayoutNode::leaf('x', new ButtonSpec('X')));
    $closed = null;
    $dialog = (new DialogControl('dlg', 'T', 'M'))->bind($surface)->onClose(function ($id) use (&$closed): void {
        $closed = $id;
    });

    $dialog->open();
    expect($surface->overlay())->not->toBeNull();
    $dialog->close('ok');
    expect($surface->overlay())->toBeNull();
    expect($closed)->toBe('ok');
});

test('registry default registers the new composite renderers', function (): void {
    $r = RendererRegistry::default();
    foreach (['list_row', 'table_row', 'tab', 'dialog_card', 'dialog_body'] as $type) {
        expect($r->has($type))->toBeTrue();
    }
});

test('semantics mapType covers the new widget types', function (): void {
    expect(SemanticsNode::mapType('list_row'))->toBe(WidgetRole::ListItem);
    expect(SemanticsNode::mapType('table_row'))->toBe(WidgetRole::ListItem);
    expect(SemanticsNode::mapType('tab'))->toBe(WidgetRole::Tab);
    expect(SemanticsNode::mapType('dialog_card'))->toBe(WidgetRole::Dialog);
    expect(SemanticsNode::mapType('dialog_body'))->toBe(WidgetRole::Group);
});
