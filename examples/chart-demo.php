<?php

declare(strict_types=1);

/**
 * Chart.js 风格自绘图表演示 (Area 自绘，无第三方图表库)
 * ────────────────────────────────────────────────────────────────────────
 * 基于 Yangweijie\Ui2\Chart\* 组件，演示：折线/柱状/饼图/环形/散点、网格与坐标
 * 轴、图例、数值标签、动画数据更新，悬停 tooltip，明/暗主题，以及手势缩放
 * （双击/框选放大、Shift+拖拽捏合、已放大后拖拽平移、+/-/0 键缩放）。
 *
 * 运行： php85 examples/chart-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Chart\Chart;
use Yangweijie\Ui2\Chart\ChartConfig;
use Yangweijie\Ui2\Chart\ChartType;
use Yangweijie\Ui2\Chart\Dataset;

$labels = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月'];

$rand = static fn () => (float) rand(20, 100);

function sampleData(): array
{
    global $rand;
    $gen = static fn () => array_map($rand, range(1, 8));

    return [
        new Dataset('营收', $gen(), null, fill: true),
        new Dataset('成本', $gen()),
        new Dataset('利润', $gen(), type: ChartType::Line),
    ];
}

$chart = new Chart(ChartType::Line, (new ChartConfig())->title('销售趋势')->showValues(false));
$chart->setLabels($labels);
$chart->setData(sampleData(), animate: false);

$area = new Area($chart);

$status = new Label('悬停查看数值 · 双击/框选=放大 · Shift+拖拽=捏合 · 放大后拖拽=平移 · 键盘 +/-/= 放大、0 复位');

$btn = static function (string $title, callable $cb): Button {
    $b = new Button($title);
    $b->onClicked($cb);

    return $b;
};

$valsOn = false;
$theme = 'light';
$top = Build::hbox(
    $btn('折线', static fn () => $chart->setType(ChartType::Line)),
    $btn('柱状', static fn () => $chart->setType(ChartType::Bar)),
    $btn('饼图', static fn () => $chart->setType(ChartType::Pie)),
    $btn('环形', static fn () => $chart->setType(ChartType::Doughnut)),
    $btn('散点', static fn () => $chart->setType(ChartType::Scatter)),
    $btn('随机数据', static fn () => $chart->setData(sampleData(), animate: true)),
    $btn('数值标签', static function () use (&$valsOn, $chart): void {
        $valsOn = ! $valsOn;
        $chart->getConfig()->showValues = $valsOn;
        $chart->redraw();
    }),
    $btn('主题', static function () use (&$theme, $chart): void {
        $theme = $theme === 'light' ? 'dark' : 'light';
        $chart->setTheme($theme);
    }),
    $btn('重置缩放', static fn () => $chart->resetZoom()),
    Build::stretchy(new Label('')),
);

$window = new Window('Chart.js 风格自绘图表', 920, 640, false);
$window->setMargined(true);
$window->centered();
$window->setChild(Build::vbox($top, Build::stretchy($area), $status));

App::new()
    ->window($window)
    ->onShouldQuit(static fn (): bool => true)
    ->run();
