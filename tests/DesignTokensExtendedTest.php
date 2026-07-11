<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;

test('phase 10 interaction tokens resolve', function (): void {
    $t = new DesignTokens();

    // hover wash defaults to a darkening overlay (light theme)
    $hover = $t->hoverWash();
    expect($hover->r)->toBe(0.0);
    expect($hover->a)->toBe(0.06);

    // disabled wash
    $disabled = $t->disabledWash();
    expect($disabled->a)->toBe(0.04);

    // focus ring
    $ring = $t->focusRing();
    expect($ring->b)->toBe(1.0);

    // numeric tokens
    expect($t->hairlineWidth())->toBe(1.0);
    expect($t->focusRingWidth())->toBe(2.0);
    expect($t->focusRingGap())->toBe(3.0);
});

test('dark theme inverts surfaces and flips the hover wash', function (): void {
    $light = new DesignTokens();
    $dark = DesignTokens::dark();

    // surfaces invert
    expect($light->color('color.surface')->r)->toBe(1.0);
    expect($dark->color('color.surface')->r)->toBe(0.12);

    // onSurface inverts
    expect($light->color('color.onSurface')->r)->toBe(0.20);
    expect($dark->color('color.onSurface')->r)->toBe(0.92);

    // hover wash flips from darken (black) to lighten (white)
    expect($light->hoverWash()->r)->toBe(0.0);
    expect($dark->hoverWash()->r)->toBe(1.0);

    // existing widget colours are preserved on the dark tree
    expect($dark->color('color.knob')->r)->toBe(1.0);
    expect($dark->hairlineWidth())->toBe(1.0);
});

test('snapHairlineRect aligns edges to device pixels', function (): void {
    // at scale 2, 0.3 → 0.5, 10.7 → 10.5, so width snaps to 10.0
    [$x, $y, $w, $h] = DesignTokens::snapHairlineRect(0.3, 0.3, 10.4, 5.6, 2.0);

    expect($x)->toBe(0.5);
    expect($y)->toBe(0.5);
    expect($w)->toBe(10.0);
    expect($h)->toBe(5.5);
});

test('ButtonRenderer draws a token-driven wash when hovered', function (): void {
    $renderer = new ButtonRenderer();
    $tokens = new DesignTokens();

    $plain = $renderer->shapeCommands(new ButtonSpec('Go'), $tokens, 100, 36);
    $hovered = $renderer->shapeCommands(new ButtonSpec('Go', hovered: true), $tokens, 100, 36);

    // hovered adds exactly one extra FillRoundedRect (the wash overlay)
    expect(count($hovered))->toBe(count($plain) + 1);

    $disabled = $renderer->shapeCommands(new ButtonSpec('Go', enabled: false), $tokens, 100, 36);
    expect(count($disabled))->toBe(count($plain) + 1);
});
