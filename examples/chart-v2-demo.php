<?php

declare(strict_types=1);

/**
 * ChartV2 自绘图表示例
 * ────────────────────────────────────────────────────────────────────────
 * 基于 Yangweijie\Ui2\ChartV2\* 组件，演示：柱状图、折线图、饼图、散点图
 * 支持：明暗主题切换、随机数据更新、鼠标悬停 Tooltip、数值标签
 *
 * 运行：php85 examples/chart-v2-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\ChartV2\ChartWidget;
use Yangweijie\Ui2\ChartV2\ChartData;
use Yangweijie\Ui2\ChartV2\ChartSeries;
use Yangweijie\Ui2\Rendering\DesignTokens;

// ── 示例数据 ──────────────────────────────────────────────────────────

$months = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月'];

function makeBarData(): array
{
    return [
        new ChartSeries('营收', 'bar', [
            ['value' => 65.0], ['value' => 78.0], ['value' => 52.0],
            ['value' => 91.0], ['value' => 85.0], ['value' => 72.0],
            ['value' => 95.0], ['value' => 88.0],
        ], 0x3B82F6),
        new ChartSeries('成本', 'bar', [
            ['value' => 42.0], ['value' => 55.0], ['value' => 38.0],
            ['value' => 60.0], ['value' => 48.0], ['value' => 51.0],
            ['value' => 58.0], ['value' => 45.0],
        ], 0xEF4444),
    ];
}

function makeLineData(): array
{
    return [
        new ChartSeries('温度', 'line', [
            ['value' => 12.0], ['value' => 15.0], ['value' => 20.0],
            ['value' => 25.0], ['value' => 30.0], ['value' => 35.0],
            ['value' => 32.0], ['value' => 28.0],
        ], 0xF59E0B),
        new ChartSeries('降水', 'area', [
            ['value' => 80.0], ['value' => 65.0], ['value' => 45.0],
            ['value' => 30.0], ['value' => 20.0], ['value' => 15.0],
            ['value' => 25.0], ['value' => 40.0],
        ], 0x3B82F6),
    ];
}

function makePieData(): array
{
    return [
        new ChartSeries('份额', 'pie', [
            ['value' => 35.0, 'label' => '产品A'],
            ['value' => 25.0, 'label' => '产品B'],
            ['value' => 20.0, 'label' => '产品C'],
            ['value' => 12.0, 'label' => '产品D'],
            ['value' => 8.0, 'label' => '其他'],
        ]),
    ];
}

function makeScatterData(): array
{
    $points = [];
    for ($i = 0; $i < 30; $i++) {
        $points[] = ['value' => rand(10, 90)];
    }

    return [
        new ChartSeries('样本 A', 'scatter', array_map(
            fn () => ['value' => rand(10, 90)],
            range(1, 20),
        ), 0x8B5CF6),
        new ChartSeries('样本 B', 'scatter', array_map(
            fn () => ['value' => rand(10, 90)],
            range(1, 20),
        ), 0x10B981),
    ];
}

function randData(): array
{
    $gen = fn () => array_map(fn () => ['value' => (float) rand(20, 100)], range(1, 8));

    return [
        new ChartSeries('系列 1', 'bar', $gen(), 0x3B82F6),
        new ChartSeries('系列 2', 'bar', $gen(), 0xEF4444),
        new ChartSeries('系列 3', 'line', $gen(), 0x10B981),
    ];
}

// ── 初始化 ────────────────────────────────────────────────────────────

$data = ChartData::create(makeBarData(), $months);
$data->title = '销售数据';
$data->theme = 'light';
$data->showValueLabels = true;

$tokens = new DesignTokens();
$chart = new ChartWidget(null, $data, $tokens);
$area = new Area($chart);
$chart->bindArea($area);

// ── UI ────────────────────────────────────────────────────────────────

$btn = static function (string $title, callable $cb): Button {
    $b = new Button($title);
    $b->onClicked($cb);

    return $b;
};

$chartConfigs = [
    '柱状图' => ['bar', makeBarData(), $months],
    '折线图' => ['line', makeLineData(), $months],
    '面积图' => ['area', makeLineData(), $months],
    '饼图'   => ['pie', makePieData(), []],
    '散点图' => ['scatter', makeScatterData(), $months],
];

$typeButtons = [];
foreach ($chartConfigs as $label => [$type, $cfgData, $cfgLabels]) {
    $typeButtons[] = $btn($label, static function () use ($chart, $type, $cfgData, $cfgLabels): void {
        $chart->setData($cfgData)->setLabels($cfgLabels)->setType($type)->setTitle($type . ' — 销售数据');
    });
}

$topChildren = array_merge(
    $typeButtons,
    [
        Build::stretchy(new Label('')),
        $btn('随机数据', static function () use ($chart, $months): void {
            $chart->setData(randData())->setLabels($months);
        }),
        $btn('明/暗主题', static function () use ($chart, $data): void {
            $newTheme = $data->theme === 'light' ? 'dark' : 'light';
            $chart->applyTheme($newTheme);
        }),
        $btn('重新配色', static function () use ($chart): void {
            // Randomize each series color
            foreach ($chart->getData()->series as $series) {
                $series->color = (rand(0, 255) << 16) | (rand(0, 255) << 8) | rand(0, 255);
            }
            $chart->redraw();
        }),
        $btn('数值标签', static function () use ($chart, $data): void {
            $data->showValueLabels = ! $data->showValueLabels;
            $chart->redraw();
        }),
    ],
);

$top = Build::hbox(...$topChildren);

$status = new Label('ChartV2 自绘 · 柱状/折线/面积/饼图/散点 · 明暗主题 · 随机数据 · 重新配色');

$window = new Window('ChartV2 自绘图表示例', 920, 620, false);
$window->setMargined(true);
$window->centered();
$window->setChild(Build::vbox($top, Build::stretchy($area), $status));

App::new()
    ->window($window)
    ->onShouldQuit(static fn (): bool => true)
    ->run();
