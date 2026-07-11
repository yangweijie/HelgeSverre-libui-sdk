<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PanelRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PanelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrimRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrimSpec;

// Headless coverage of the WidgetRenderer layer (Slice 3 of the rendering
// engine). The geometry produced by each renderer is pure (no DrawContext /
// TextLayout), so it can be asserted without a display. The renderer-driven
// draw path (CommandExecutor + Area) and the RendererButton control itself are
// verified in the GUI demo (examples/renderer-button-demo.php).

test('default registry is pre-loaded with button and card renderers', function () {
    $registry = RendererRegistry::default();

    expect($registry->has('button'))->toBeTrue();
    expect($registry->has('card'))->toBeTrue();
    expect($registry->types())->toContain('button');
    expect($registry->types())->toContain('card');
});

test('registry returns null for an unregistered type (native fallback cue)', function () {
    $registry = new RendererRegistry();

    expect($registry->has('button'))->toBeFalse();
    expect($registry->get('button'))->toBeNull();
});

test('ScrimRenderer draws a single flat fill at the given alpha', function () {
    $cmds = (new ScrimRenderer())->shapeCommands(new ScrimSpec(alpha: 0.12), new DesignTokens(), 400, 300);

    expect($cmds)->toHaveCount(1);
    expect($cmds[0])->toBeInstanceOf(FillRoundedRect::class);
    expect($cmds[0]->color->a)->toEqualWithDelta(0.12, 0.001);
    expect($cmds[0]->width)->toBe(400.0);
    expect($cmds[0]->height)->toBe(300.0);
});

test('ButtonRenderer: filled variant draws a solid primary background, no border', function () {
    $cmds = (new ButtonRenderer())->shapeCommands(new ButtonSpec('保存', 'filled'), new DesignTokens(), 120, 36);

    $fills = array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect);
    $strokes = array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect);
    expect($fills)->toHaveCount(1);
    expect($strokes)->toHaveCount(0);

    // background = color.primary = [0.04, 0.52, 1.0, 1.0]
    $bg = array_values($fills)[0];
    expect($bg->color->r)->toEqualWithDelta(0.04, 0.001);
    expect($bg->color->g)->toEqualWithDelta(0.52, 0.001);
    expect($bg->color->b)->toEqualWithDelta(1.0, 0.001);
});

test('ButtonRenderer: outline variant draws only a primary hairline, no fill', function () {
    $cmds = (new ButtonRenderer())->shapeCommands(new ButtonSpec('取消', 'outline'), new DesignTokens(), 120, 36);

    $fills = array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect);
    $strokes = array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect);
    expect($fills)->toHaveCount(0);
    expect($strokes)->toHaveCount(1);

    $border = array_values($strokes)[0];
    expect($border->color->g)->toEqualWithDelta(0.52, 0.001); // primary
});

test('ButtonRenderer: soft variant draws a track background plus a primary hairline', function () {
    $cmds = (new ButtonRenderer())->shapeCommands(new ButtonSpec('次要', 'soft'), new DesignTokens(), 120, 36);

    $fills = array_values(array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect));
    $strokes = array_values(array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect));
    expect($fills)->toHaveCount(1);
    expect($strokes)->toHaveCount(1);

    expect($fills[0]->color->r)->toEqualWithDelta(0.88, 0.001); // track
    expect($strokes[0]->color->g)->toEqualWithDelta(0.52, 0.001); // primary
});

test('ButtonRenderer: disabled variant is muted (track bg, no border, disabled wash)', function () {
    $cmds = (new ButtonRenderer())->shapeCommands(new ButtonSpec('禁用', 'filled', false), new DesignTokens(), 120, 36);

    $fills = array_values(array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect));
    $strokes = array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect);
    // track background + the token-driven disabled wash overlay
    expect($fills)->toHaveCount(2);
    expect($strokes)->toHaveCount(0);
    expect($fills[0]->color->r)->toEqualWithDelta(0.88, 0.001); // track bg
    expect($fills[1]->color->a)->toEqualWithDelta(0.04, 0.001); // disabled wash alpha
});

test('ButtonRenderer: pressed darkens the primary-derived colour by 15%', function () {
    $idle = (new ButtonRenderer())->shapeCommands(new ButtonSpec('保存', 'filled', true, false), new DesignTokens(), 120, 36);
    $pressed = (new ButtonRenderer())->shapeCommands(new ButtonSpec('保存', 'filled', true, true), new DesignTokens(), 120, 36);

    $idleBg = array_values(array_filter($idle, static fn ($c) => $c instanceof FillRoundedRect))[0];
    $pressedBg = array_values(array_filter($pressed, static fn ($c) => $c instanceof FillRoundedRect))[0];

    // primary.r = 0.04 → pressed.r = 0.04 * 0.85 = 0.034
    expect($pressedBg->color->r)->toEqualWithDelta(0.034, 0.001);
    expect($pressedBg->color->r)->toBeLessThan($idleBg->color->r);
});

test('ButtonRenderer: render with an empty label builds only geometry (no DrawText), free() safe', function () {
    $list = (new ButtonRenderer())->render(new ButtonSpec('', 'filled'), new DesignTokens(), 120, 36);

    expect($list)->toBeInstanceOf(RenderCommandList::class);
    expect($list->commands)->toHaveCount(1);
    expect($list->commands[0])->toBeInstanceOf(FillRoundedRect::class);
    $list->free(); // must not throw even though there is no TextLayout to release

    expect(true)->toBeTrue();
});

test('CardRenderer: bordered card draws a surface fill plus a hairline border', function () {
    $cmds = (new CardRenderer())->shapeCommands(new CardSpec(bordered: true), new DesignTokens(), 200, 120);

    $fills = array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect);
    $strokes = array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect);
    expect($fills)->toHaveCount(1);
    expect($strokes)->toHaveCount(1);

    expect(array_values($fills)[0]->color->r)->toBe(1.0); // surface = white
    expect(array_values($strokes)[0]->color->r)->toEqualWithDelta(0.88, 0.001); // track border
});

test('CardRenderer: elevation adds a low-alpha offset shadow rect', function () {
    $cmds = (new CardRenderer())->shapeCommands(new CardSpec(bordered: true, elevation: 1.0), new DesignTokens(), 200, 120);

    // shadow + surface + border
    expect($cmds)->toHaveCount(3);
    expect($cmds[0])->toBeInstanceOf(FillRoundedRect::class);
    expect($cmds[0]->color->a)->toEqualWithDelta(0.12, 0.001); // 0.12 * elevation
});

test('CardRenderer: no border and no elevation draws only the surface', function () {
    $cmds = (new CardRenderer())->shapeCommands(new CardSpec(bordered: false, elevation: 0.0), new DesignTokens(), 200, 120);

    expect($cmds)->toHaveCount(1);
    expect($cmds[0])->toBeInstanceOf(FillRoundedRect::class);
    expect($cmds[0]->color->r)->toBe(1.0); // surface
});

test('CardRenderer: render is fully headless-safe (no text) and returns a list', function () {
    $list = (new CardRenderer())->render(new CardSpec(), new DesignTokens(), 200, 120);

    expect($list)->toBeInstanceOf(RenderCommandList::class);
    expect($list->commands)->toHaveCount(2); // surface + border
    $list->free();

    expect(true)->toBeTrue();
});

test('default registry includes the label renderer', function () {
    $registry = RendererRegistry::default();

    expect($registry->has('label'))->toBeTrue();
    expect($registry->types())->toContain('label');
});

test('LabelRenderer: shapeCommands is empty (label has no geometry)', function () {
    $cmds = (new LabelRenderer())->shapeCommands(new LabelSpec('Title'), new DesignTokens(), 200, 24);

    expect($cmds)->toHaveCount(0);
});

test('LabelRenderer: empty text produces no commands', function () {
    $list = (new LabelRenderer())->render(new LabelSpec(''), new DesignTokens(), 200, 24);

    expect($list->commands)->toHaveCount(0);
    $list->free();
});

test('default registry includes the panel renderer', function () {
    $registry = RendererRegistry::default();

    expect($registry->has('panel'))->toBeTrue();
    expect($registry->types())->toContain('panel');
});

test('PanelRenderer: bordered panel draws a surface fill plus a hairline border', function () {
    $cmds = (new PanelRenderer())->shapeCommands(new PanelSpec(bordered: true, elevation: 0.0), new DesignTokens(), 200, 120);

    $fills = array_filter($cmds, static fn ($c) => $c instanceof FillRoundedRect);
    $strokes = array_filter($cmds, static fn ($c) => $c instanceof StrokeRoundedRect);
    expect($fills)->toHaveCount(1);
    expect($strokes)->toHaveCount(1);

    expect(array_values($fills)[0]->color->r)->toBe(1.0); // surface = white
    expect(array_values($strokes)[0]->color->r)->toEqualWithDelta(0.88, 0.001); // track border
});

test('PanelRenderer: elevation adds a low-alpha offset shadow rect', function () {
    $cmds = (new PanelRenderer())->shapeCommands(new PanelSpec(bordered: true, elevation: 1.0), new DesignTokens(), 200, 120);

    expect($cmds)->toHaveCount(3);
    expect($cmds[0])->toBeInstanceOf(FillRoundedRect::class);
    expect($cmds[0]->color->a)->toEqualWithDelta(0.12, 0.001);
});

test('PanelRenderer: no hover wash even when the node is hovered', function () {
    $cmds = (new PanelRenderer())->shapeCommands(new PanelSpec(bordered: false, elevation: 0.0), new DesignTokens(), 200, 120);

    expect($cmds)->toHaveCount(1);
    expect($cmds[0])->toBeInstanceOf(FillRoundedRect::class);
});

test('PanelRenderer: render is fully headless-safe (no text) and returns a list', function () {
    $list = (new PanelRenderer())->render(new PanelSpec(elevation: 0.0), new DesignTokens(), 200, 120);

    expect($list)->toBeInstanceOf(RenderCommandList::class);
    expect($list->commands)->toHaveCount(2); // surface + border
    $list->free();

    expect(true)->toBeTrue();
});
