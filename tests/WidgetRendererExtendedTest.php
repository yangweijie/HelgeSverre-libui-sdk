<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillPolygon;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\StrokeCircle;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldRenderer;

/** @return list<string> the class name of each command in $list */
function commandTypes(object $list): array
{
    return array_map(get_class(...), $list->commands);
}

$tokens = new DesignTokens();

test('registry default registers the extended widget renderers', function (): void {
    $r = \Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry::default();
    foreach (['button', 'card', 'checkbox', 'radio', 'slider', 'progress', 'text_field', 'select'] as $type) {
        expect($r->has($type))->toBeTrue();
    }
});

test('checkbox checked draws a primary fill, border and a two-segment check', function () use ($tokens): void {
    $cmds = (new CheckboxRenderer())->shapeCommands(new CheckboxSpec(checked: true), $tokens, 120, 24);
    $types = array_map(get_class(...), $cmds);

    expect($types)->toContain(FillRoundedRect::class);
    expect($types)->toContain(StrokeRoundedRect::class);
    expect(count(array_filter($cmds, fn ($c) => $c instanceof StrokeLine)))->toBe(2);
});

test('checkbox unchecked draws a track fill and border, no checkmark', function () use ($tokens): void {
    $cmds = (new CheckboxRenderer())->shapeCommands(new CheckboxSpec(checked: false), $tokens, 120, 24);
    expect(count(array_filter($cmds, fn ($c) => $c instanceof StrokeLine)))->toBe(0);
});

test('radio selected draws a ring plus a primary inner dot', function () use ($tokens): void {
    $cmds = (new RadioRenderer())->shapeCommands(new RadioSpec(selected: true), $tokens, 120, 24);
    $types = array_map(get_class(...), $cmds);
    expect($types)->toContain(StrokeCircle::class);
    expect($types)->toContain(FillCircle::class);
});

test('slider draws a track, a fill portion and a thumb', function () use ($tokens): void {
    $cmds = (new SliderRenderer())->shapeCommands(new SliderSpec(value: 0.5), $tokens, 200, 24);
    $types = array_map(get_class(...), $cmds);
    expect(count(array_filter($cmds, fn ($c) => $c instanceof FillRoundedRect)))->toBe(2);
    expect($types)->toContain(FillCircle::class);
    expect($types)->toContain(StrokeCircle::class);
});

test('progress at 50% draws a track and a half-width fill', function () use ($tokens): void {
    $cmds = (new ProgressRenderer())->shapeCommands(new ProgressSpec(value: 0.5), $tokens, 200, 8);
    $fills = array_filter($cmds, fn ($c) => $c instanceof FillRoundedRect);
    expect(count($fills))->toBe(2);
    $fill = array_values($fills)[1];
    expect($fill->width)->toEqualWithDelta(100.0, 0.001);
});

test('text_field draws a surface fill and a border', function () use ($tokens): void {
    $cmds = (new TextFieldRenderer())->shapeCommands(new TextFieldSpec(focused: true), $tokens, 200, 30);
    $types = array_map(get_class(...), $cmds);
    expect($types)->toContain(FillRoundedRect::class);
    expect($types)->toContain(StrokeRoundedRect::class);
});

test('select draws a fill, border and a caret polygon', function () use ($tokens): void {
    $cmds = (new SelectRenderer())->shapeCommands(new SelectSpec(value: 'Apple'), $tokens, 160, 30);
    $types = array_map(get_class(...), $cmds);
    expect($types)->toContain(FillRoundedRect::class);
    expect($types)->toContain(StrokeRoundedRect::class);
    expect($types)->toContain(FillPolygon::class);
});

test('disabled variants mute the primary-derived paint', function () use ($tokens): void {
    $enabled = (new SliderRenderer())->shapeCommands(new SliderSpec(value: 0.5, enabled: true), $tokens, 200, 24);
    $disabled = (new SliderRenderer())->shapeCommands(new SliderSpec(value: 0.5, enabled: false), $tokens, 200, 24);
    // The fill portion color alpha should drop when disabled.
    $enabledFill = array_values(array_filter($enabled, fn ($c) => $c instanceof FillRoundedRect))[1];
    $disabledFill = array_values(array_filter($disabled, fn ($c) => $c instanceof FillRoundedRect))[1];
    expect($disabledFill->color->a)->toBeLessThan($enabledFill->color->a);
});
