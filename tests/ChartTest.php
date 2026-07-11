<?php

declare(strict_types=1);

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
