<?php

declare(strict_types=1);

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeArc;
use Yangweijie\Ui2\Widgets\CircleProgressDelegate;

// Headless coverage of the command-compilation layer.
// arcCommands() contains no DrawContext / TextLayout work (those need a live
// libui context), so the geometry and colour fidelity of the ring can be
// asserted without a display. The full buildCommands()/Executor path is
// verified in the GUI demo.

test('delegate emits a clean ring: track + progress arc', function () {
    $d = new CircleProgressDelegate(65, 200);
    $cmds = $d->arcCommands(300, 200);

    // track (full circle) + progress arc
    expect($cmds)->toHaveCount(2);
    expect($cmds[0])->toBeInstanceOf(StrokeArc::class);
    expect($cmds[1])->toBeInstanceOf(StrokeArc::class);

    // track is a full ring (2π sweep) starting at angle 0
    expect($cmds[0]->startAngle)->toBe(0.0);
    expect($cmds[0]->sweep)->toEqualWithDelta(2 * M_PI, 0.001);

    // progress arc starts at -π/2 (top) and sweeps ~65% of the circle
    expect($cmds[1]->startAngle)->toEqualWithDelta(-M_PI / 2, 0.001);
    expect($cmds[1]->sweep)->toEqualWithDelta(0.65 * 2 * M_PI, 0.001);

    // both share the same centre and radius
    expect($cmds[0]->cx)->toBe($cmds[1]->cx);
    expect($cmds[0]->cy)->toBe($cmds[1]->cy);
    expect($cmds[0]->radius)->toBe($cmds[1]->radius);
});

test('progress 0 emits only the track arc', function () {
    $d = new CircleProgressDelegate(0, 200);
    $cmds = $d->arcCommands(200, 200);

    expect($cmds)->toHaveCount(1);
    expect($cmds[0])->toBeInstanceOf(StrokeArc::class);
    expect($cmds[0]->sweep)->toEqualWithDelta(2 * M_PI, 0.001);
});

test('smaller viewport falls back to content size and re-centres the ring', function () {
    $d = new CircleProgressDelegate(50, 200);

    // 0×0 viewport → geometry uses ringSize (200), centre at (100, 100)
    $small = $d->arcCommands(0, 0);
    expect($small[0]->cx)->toBe(100.0);
    expect($small[0]->cy)->toBe(100.0);

    // normal viewport → centre at its own midpoint
    $normal = $d->arcCommands(300, 200);
    expect($normal[0]->cx)->toBe(150.0);
    expect($normal[0]->cy)->toBe(100.0);
});

test('ring colours match the hard-coded token values (pixel fidelity)', function () {
    $d = new CircleProgressDelegate(65, 200);
    $cmds = $d->arcCommands(200, 200);

    // TRACK_COLOR = [0.88, 0.88, 0.88, 1.0]
    expect($cmds[0]->color->r)->toEqualWithDelta(0.88, 0.001);
    expect($cmds[0]->color->g)->toEqualWithDelta(0.88, 0.001);
    expect($cmds[0]->color->b)->toEqualWithDelta(0.88, 0.001);

    // DEFAULT_COLOR = [0.04, 0.52, 1.0, 1.0] (the progress arc)
    expect($cmds[1]->color->r)->toEqualWithDelta(0.04, 0.001);
    expect($cmds[1]->color->g)->toEqualWithDelta(0.52, 0.001);
    expect($cmds[1]->color->b)->toEqualWithDelta(1.0, 0.001);
});

test('RenderCommandList::free is safe on an empty list', function () {
    $list = new RenderCommandList([]);
    $list->free(); // must not throw

    expect(true)->toBeTrue();
});

test('theme override changes the resolved progress arc colour', function () {
    $d = new CircleProgressDelegate(65, 200);
    $d->tokens = $d->tokens->applyTheme(['color' => ['primary' => [0.0, 0.8, 0.0, 1.0]]]);
    $cmds = $d->arcCommands(200, 200);

    // progress arc picks up the override
    expect($cmds[1]->color->r)->toEqualWithDelta(0.0, 0.001);
    expect($cmds[1]->color->g)->toEqualWithDelta(0.8, 0.001);
    // track is untouched
    expect($cmds[0]->color->r)->toEqualWithDelta(0.88, 0.001);
});

test('explicit setColor overrides the theme token', function () {
    $d = new CircleProgressDelegate(65, 200);
    $d->tokens = $d->tokens->applyTheme(['color' => ['primary' => [0.0, 0.8, 0.0, 1.0]]]);
    $d->color = Color::rgb(0xFF0000);

    expect($d->progressColor()->r)->toEqualWithDelta(1.0, 0.001);
    expect($d->progressColor()->g)->toEqualWithDelta(0.0, 0.001);
});

test('StrokeArc carries the stroke params needed for a round-cap ring', function () {
    $d = new CircleProgressDelegate(65, 200);
    $cmd = $d->arcCommands(200, 200)[1];

    expect($cmd)->toBeInstanceOf(StrokeArc::class);
    expect($cmd->stroke->thickness)->toBe(12.0);
    expect($cmd->stroke->cap)->toBe(\Libui\Generated\Enum\DrawLineCap::Round);
});
