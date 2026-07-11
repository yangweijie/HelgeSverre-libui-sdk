<?php

declare(strict_types=1);

use Libui\Color;
use Yangweijie\Ui2\Chart\Animator;
use Yangweijie\Ui2\Chart\BarRenderer;
use Yangweijie\Ui2\Chart\Chart;
use Yangweijie\Ui2\Chart\ChartConfig;
use Yangweijie\Ui2\Chart\ChartType;
use Yangweijie\Ui2\Chart\ChartView;
use Yangweijie\Ui2\Chart\Dataset;
use Yangweijie\Ui2\Chart\LineRenderer;
use Yangweijie\Ui2\Chart\PieRenderer;
use Yangweijie\Ui2\Chart\RendererFactory;
use Yangweijie\Ui2\Chart\Scale;
use Yangweijie\Ui2\Chart\ZoomState;

test('chart constructs and reports its type', function () {
    $chart = new Chart(ChartType::Bar);
    expect($chart->getType())->toBe(ChartType::Bar);
    expect($chart->getConfig())->toBeInstanceOf(ChartConfig::class);
});

test('setData (no animation) updates displayed values, preserving nulls', function () {
    $chart = new Chart(ChartType::Line);
    $chart->setLabels(['a', 'b', 'c']);
    $chart->setData([new Dataset('销售', [3, 7, 5])], animate: false);

    expect($chart->getDisplayValues()[0])->toBe([3.0, 7.0, 5.0]);

    $chart->setData([new Dataset('x', [1, null, 3])], animate: false);
    expect($chart->getDisplayValues()[0][1])->toBeNull();
    expect($chart->getDisplayValues()[0][0])->toBe(1.0);
});

test('setData grows / shrinks the series count', function () {
    $chart = new Chart(ChartType::Bar);
    $chart->setData([new Dataset('x', [1, 2, 3, 4, 5])], animate: false);
    expect(count($chart->getDisplayValues()[0]))->toBe(5);

    $chart->setData([new Dataset('x', [9, 8])], animate: false);
    expect(count($chart->getDisplayValues()[0]))->toBe(2);
});

test('scale produces nice bounded ticks', function () {
    $s = Scale::forValues([0, 20, 40, 100], true);
    expect($s->min)->toBeLessThanOrEqual(0.0);
    expect($s->max)->toBeGreaterThanOrEqual(100.0);

    $ticks = $s->ticks(5);
    expect($ticks)->not->toBeEmpty();
    foreach ($ticks as $t) {
        expect($t)->toBeGreaterThanOrEqual($s->min - 1e-6);
        expect($t)->toBeLessThanOrEqual($s->max + 1e-6);
    }
    // nice number of ticks (not hundreds, not one)
    $c = count($ticks);
    expect($c)->toBeGreaterThanOrEqual(2);
    expect($c)->toBeLessThanOrEqual(12);
});

test('zoomAt keeps the anchor centred and clamps to full domain', function () {
    $z = new ZoomState();
    $z->setFull(0.0, 10.0, 0.0, 100.0);

    $z->zoomAt(2.0, 5.0, 50.0);
    expect($z->xMin)->toEqualWithDelta(2.5, 0.001);
    expect($z->xMax)->toEqualWithDelta(7.5, 0.001);
    expect($z->active)->toBeTrue();

    // over-zoom is clamped by maxZoom and cannot exceed the full domain
    $z->reset();
    expect($z->xMin)->toBe(0.0);
    expect($z->xMax)->toBe(10.0);
    expect($z->active)->toBeFalse();
});

test('pan only shifts a zoomed-in viewport (full-width pan is a no-op)', function () {
    $z = new ZoomState();
    $z->setFull(0.0, 10.0, 0.0, 100.0);

    // zoom in first so the domain is narrower than full
    $z->zoomAt(2.0, 5.0, 50.0); // x: 2.5 .. 7.5
    $z->pan(0.1, 0.0);
    expect($z->xMin)->toEqualWithDelta(2.0, 0.001);
    expect($z->xMax)->toEqualWithDelta(7.0, 0.001);

    // panning at full zoom cannot leave the data bounds
    $z->reset();
    $z->pan(0.5, 0.0);
    expect($z->xMin)->toBe(0.0);
    expect($z->xMax)->toBe(10.0);
});

test('zoomTo commits an explicit box-zoom domain and clamps to full', function () {
    $z = new ZoomState();
    $z->setFull(0.0, 10.0, 0.0, 100.0);

    // box-zoom into the middle region
    $z->zoomTo(2.0, 6.0, 20.0, 80.0);
    expect($z->active)->toBeTrue();
    expect($z->xMin)->toEqualWithDelta(2.0, 1e-6);
    expect($z->xMax)->toEqualWithDelta(6.0, 1e-6);
    expect($z->yMin)->toEqualWithDelta(20.0, 1e-6);
    expect($z->yMax)->toEqualWithDelta(80.0, 1e-6);

    // a selection wider than the full domain snaps back to full
    $z->zoomTo(-5.0, 50.0, -10.0, 200.0);
    expect($z->xMin)->toEqualWithDelta(0.0, 1e-6);
    expect($z->xMax)->toEqualWithDelta(10.0, 1e-6);
    expect($z->yMin)->toEqualWithDelta(0.0, 1e-6);
    expect($z->yMax)->toEqualWithDelta(100.0, 1e-6);
    expect($z->active)->toBeFalse();
});

test('animator interpolates with ease-out cubic and pads mismatched lengths', function () {
    expect(Animator::easeOutCubic(0.0))->toEqualWithDelta(0.0, 1e-6);
    expect(Animator::easeOutCubic(1.0))->toEqualWithDelta(1.0, 1e-6);
    expect(Animator::easeOutCubic(0.5))->toEqualWithDelta(0.875, 1e-6);

    $lerp = Animator::lerp([[0.0], [10.0]], [[0.0], [20.0]], 0.875);
    expect($lerp[1][0])->toEqualWithDelta(18.75, 1e-6);

    // mismatched lengths pad with 0 -> new point grows from zero
    $grow = Animator::lerp([[1.0, 2.0]], [[1.0, 2.0, 9.0]], 1.0);
    expect($grow[0])->toBe([1.0, 2.0, 9.0]);
});

test('animator tween is seekable for deterministic transitions', function () {
    $anim = new Animator();
    $captured = [];
    $done = false;
    $anim->animate(
        [[0.0], [10.0]],
        [[0.0], [20.0]],
        600.0,
        static function (array $v) use (&$captured): void {
            $captured = $v;
        },
        static function () use (&$done): void {
            $done = true;
        },
    );
    $anim->seekTo(0.5);
    expect($captured[1][0])->toEqualWithDelta(18.75, 0.01);
    $anim->seekTo(1.0);
    expect($captured[1][0])->toEqualWithDelta(20.0, 0.001);
    expect($done)->toBeTrue();
});

test('chart view pixel<->data transforms round-trip', function () {
    $view = new ChartView(new ChartConfig());
    $view->plot = [100.0, 50.0, 400.0, 300.0];
    $view->xMin = 0.0;
    $view->xMax = 10.0;
    $view->yMin = 0.0;
    $view->yMax = 100.0;

    $px = $view->xToPx(5.0);
    expect($px)->toEqualWithDelta(300.0, 0.001);
    expect($view->pxToX($px))->toEqualWithDelta(5.0, 0.001);

    $py = $view->yToPx(50.0);
    expect($py)->toEqualWithDelta(200.0, 0.001);
    expect($view->pxToY($py))->toEqualWithDelta(50.0, 0.001);
});

test('renderer factory maps each chart type to its renderer', function () {
    expect(RendererFactory::make(ChartType::Line))->toBeInstanceOf(LineRenderer::class);
    expect(RendererFactory::make(ChartType::Scatter))->toBeInstanceOf(LineRenderer::class);
    expect(RendererFactory::make(ChartType::Bar))->toBeInstanceOf(BarRenderer::class);
    expect(RendererFactory::make(ChartType::Pie))->toBeInstanceOf(PieRenderer::class);
    expect(RendererFactory::make(ChartType::Doughnut))->toBeInstanceOf(PieRenderer::class);
});

test('setType resets zoom so a fresh chart starts unzoomed', function () {
    $chart = new Chart(ChartType::Line);
    $chart->setData([new Dataset('x', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10])], animate: false);
    $chart->getZoom()->zoomAt(2.0, 5.0, 5.0);
    expect($chart->getZoom()->active)->toBeTrue();

    $chart->setType(ChartType::Bar);
    expect($chart->getZoom()->active)->toBeFalse();
    expect($chart->getType())->toBe(ChartType::Bar);
});

test('chartConfig applies light and dark theme palettes', function () {
    $c = new ChartConfig();
    $c->applyTheme('dark');
    expect($c->theme)->toBe('dark');
    expect($c->background)->toBe(0x0F172A);
    expect($c->axisLabelColor)->toBe(0x94A3B8);
    expect($c->tooltipBackground)->toBe(0xF8FAFC);
    expect($c->tooltipText)->toBe(0x0F172A);

    $c->applyTheme('light');
    expect($c->theme)->toBe('light');
    expect($c->background)->toBe(0xFFFFFF);
    expect($c->tooltipBackground)->toBe(0x1E293B);

    // unknown theme falls back to light
    $c->applyTheme('neon');
    expect($c->theme)->toBe('light');
});

test('chart hover starts null and setTheme switches palette safely', function () {
    $chart = new Chart(ChartType::Line);
    expect($chart->getHover())->toBeNull();

    $chart->setTheme('dark');
    expect($chart->getConfig()->theme)->toBe('dark');
    expect($chart->getConfig()->background)->toBe(0x0F172A);

    // switching back to light restores default colours
    $chart->setTheme('light');
    expect($chart->getConfig()->background)->toBe(0xFFFFFF);
});

test('Color HSL round-trips and matches known anchors', function () {
    expect(Color::hsl(0, 1.0, 0.5)->toHex())->toBe(0xFF0000); // pure red
    expect(Color::hsl(120, 1.0, 0.5)->toHex())->toBe(0x00FF00); // pure green
    expect(Color::hsl(240, 1.0, 0.5)->toHex())->toBe(0x0000FF); // pure blue

    $c = Color::hsl(210, 0.8, 0.5);
    [$h, $s, $l, $a] = $c->toHsl();
    expect($h)->toEqualWithDelta(210.0, 0.01);
    expect($s)->toEqualWithDelta(0.8, 0.01);
    expect($l)->toEqualWithDelta(0.5, 0.01);
    expect($a)->toBe(1.0);
});

test('Color lerp blends channels and alpha', function () {
    $mid = Color::red()->lerp(Color::blue(), 0.5);
    expect($mid->toHex())->toBe(0x800080); // equal red+blue
    expect($mid->a)->toBe(1.0);

    $half = Color::black()->lerp(Color::white(), 0.5);
    expect($half->r)->toEqualWithDelta(0.5, 0.001);
    expect($half->g)->toEqualWithDelta(0.5, 0.001);

    // withAlpha + mix alias behave consistently
    expect(Color::red()->mix(Color::blue(), 0.5)->toHex())->toBe(0x800080);
    expect(Color::red()->withAlpha(0.3)->a)->toEqualWithDelta(0.3, 0.001);
});

test('Color contrast picks readable foreground', function () {
    expect(Color::white()->contrastColor()->toHex())->toBe(0x000000);
    expect(Color::black()->contrastColor()->toHex())->toBe(0xFFFFFF);
    expect(Color::white()->isLight())->toBeTrue();
    expect(Color::black()->isLight())->toBeFalse();
});

test('chart palette resolves named colors, then derives variants for extra series', function () {
    $c = new ChartConfig();
    $pal = $c->palette();
    expect($pal)->toHaveCount(10);
    // first entry is slateblue (per PALETTE_NAMES)
    expect($pal[0])->toBe(Color::named(ChartConfig::PALETTE_NAMES[0])->toHex());
    expect($c->colorAt(0))->toBe($pal[0]);
    expect($c->colorAt(1))->toBe($pal[1]);

    // beyond the 10 base colours, colorAt derives a distinct light/dark variant
    expect($c->colorAt(10))->not->toBe($pal[0]);
    expect($c->colorAt(20))->not->toBe($pal[0]);
    expect($c->colorAt(10))->not->toBe($c->colorAt(20)); // different rings

    // seriesPalette(22) yields 22 mostly-distinct colours (no earlier collisions)
    $series = $c->seriesPalette(22);
    expect($series)->toHaveCount(22);
    expect(count(array_unique($series)))->toBeGreaterThanOrEqual(20);

    $custom = (new ChartConfig())->colors(0x123456, 0xABCDEF);
    expect($custom->palette())->toBe([0x123456, 0xABCDEF]);
    expect($custom->colorAt(1))->toBe(0xABCDEF);
    // custom palette also derives variants past its size
    expect($custom->colorAt(2))->not->toBe(0xABCDEF);
});

test('theme interpolation lerps every themed colour via Color::lerp', function () {
    $from = ChartConfig::THEMES['light'];
    $to = ChartConfig::THEMES['dark'];

    $expectedBg = Color::rgb($from['background'])->lerp(Color::rgb($to['background']), 0.5)->toHex();
    expect(ChartConfig::interpolateTheme($from, $to, 0.5)['background'])->toBe($expectedBg);

    expect(ChartConfig::interpolateTheme($from, $to, 0.0)['background'])->toBe(0xFFFFFF);
    expect(ChartConfig::interpolateTheme($from, $to, 1.0)['background'])->toBe(0x0F172A);

    $mid = ChartConfig::interpolateTheme($from, $to, 0.5);
    foreach (ChartConfig::THEMED_FIELDS as $f) {
        expect(array_key_exists($f, $mid))->toBeTrue();
    }
});

test('setTheme animates only with a bound area; headless snaps immediately', function () {
    $chart = new Chart(ChartType::Line);
    // no Area bound -> even with animate requested, it applies instantly
    $chart->setTheme('dark', animate: true);
    expect($chart->getConfig()->theme)->toBe('dark');
    expect($chart->getConfig()->background)->toBe(0x0F172A);

    $chart->setTheme('light');
    expect($chart->getConfig()->background)->toBe(0xFFFFFF);
});

test('recolor swaps the palette and updates series colors (headless snaps)', function () {
    $chart = new Chart(ChartType::Line);
    $chart->setData([new Dataset('A', [1, 2, 3]), new Dataset('B', [4, 5, 6])], animate: false);

    expect($chart->getConfig()->customPalette)->toBeNull();
    $chart->recolor(0x111111, 0x222222, 0x333333);
    expect($chart->getConfig()->customPalette)->toBe([0x111111, 0x222222, 0x333333]);
    // first series now takes the new palette's first colour
    expect($chart->getConfig()->colorAt(0))->toBe(0x111111);
    expect($chart->getConfig()->colorAt(1))->toBe(0x222222);
});

test('recolor() with no args reverts to the named palette', function () {
    $chart = new Chart(ChartType::Line);
    $chart->setData([new Dataset('A', [1, 2, 3])], animate: false);
    $chart->recolor(0xABCDEF);
    expect($chart->getConfig()->customPalette)->toBe([0xABCDEF]);
    $chart->recolor();
    expect($chart->getConfig()->customPalette)->toBeNull();
    expect($chart->getConfig()->colorAt(0))->toBe(Color::named(ChartConfig::PALETTE_NAMES[0])->toHex());
});

test('series colour rows round-trip through colorsToRows/rowsToColors', function () {
    $chart = new Chart(ChartType::Bar);
    $rows = (new ReflectionMethod(Chart::class, 'colorsToRows'))
        ->invoke($chart, [0xFF0000, 0x00FF00, 0x0000FF]);
    expect($rows[0])->toBe([1.0, 0.0, 0.0]);
    expect($rows[1])->toBe([0.0, 1.0, 0.0]);
    expect($rows[2])->toBe([0.0, 0.0, 1.0]);

    $back = (new ReflectionMethod(Chart::class, 'rowsToColors'))->invoke($chart, $rows);
    expect($back[0])->toBe(0xFF0000);
    expect($back[1])->toBe(0x00FF00);
    expect($back[2])->toBe(0x0000FF);
});

test('hoverPointPx resolves the data point pixel for cartesian and pie', function () {
    // cartesian: a plotted point
    $chart = new Chart(ChartType::Line);
    $view = new ChartView($chart->getConfig());
    $view->points = [[0, 0, 120.0, 240.0]];
    $hover = new ReflectionProperty(Chart::class, 'hover');
    $hover->setAccessible(true);
    $hover->setValue($chart, ['i' => 0, 'j' => 0]);
    expect((new ReflectionMethod(Chart::class, 'hoverPointPx'))->invoke($chart, $view))
        ->toBe([120.0, 240.0]);

    // pie: the slice centroid
    $pie = new Chart(ChartType::Pie);
    $pview = new ChartView($pie->getConfig());
    $pview->pieCenter = [300.0, 300.0];
    $pview->pieInner = 50.0;
    $pview->pieRadius = 100.0;
    $pview->pieSlices = [['a0' => -M_PI / 2, 'sweep' => M_PI / 2, 'ox' => 0.0, 'oy' => 0.0, 'value' => 1, 'label' => 'x', 'color' => 0]];
    $ph = new ReflectionProperty(Chart::class, 'hover');
    $ph->setAccessible(true);
    $ph->setValue($pie, ['slice' => 0]);
    $ppt = (new ReflectionMethod(Chart::class, 'hoverPointPx'))->invoke($pie, $pview);
    $mid = -M_PI / 2 + (M_PI / 2) / 2.0;
    $r = 75.0;
    expect($ppt[0])->toEqualWithDelta(300.0 + cos($mid) * $r, 1e-6);
    expect($ppt[1])->toEqualWithDelta(300.0 + sin($mid) * $r, 1e-6);
});

