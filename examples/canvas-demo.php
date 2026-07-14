<?php

declare(strict_types=1);

/**
 * CanvasSpec 示例 — 在 Surface 自绘布局中嵌入自定义绘制回调
 * ─────────────────────────────────────────────────────────────
 * 演示 CanvasSpec 将任意 DrawContext 绘制嵌入 Surface 的 LayoutNode 树：
 *   - 迷你折线图（CanvasSpec + 闭包）
 *   - 迷你柱状图（CanvasSpec + 闭包）
 *   - 自定义进度条（CanvasSpec + 闭包）
 *   - 与 LabelSpec 混合布局
 *
 * 运行：php85 examples/canvas-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\StrokeParams;
use Libui\Loop;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CanvasSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Widgets\Surface;

// ═════════════════════════════════════════════════════════════════════════════
// DATA
// ═════════════════════════════════════════════════════════════════════════════

$lineData = [45, 72, 55, 88, 65, 95, 78, 60, 85, 70];
$barData = [30, 55, 40, 75, 60, 85, 50, 70];
$progress = 0.0;

// ═════════════════════════════════════════════════════════════════════════════
// CANVAS SPECS — 自定义绘制回调
// ═════════════════════════════════════════════════════════════════════════════

/** Mini line chart canvas */
$lineChartSpec = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h) use ($lineData): void {
        fwrite(STDERR, "lineChart callback: {$w}x{$h}\n");
        // Background
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));

        $count = count($lineData);
        $max = max($lineData);
        $pad = 12.0;
        $plotW = $w - 2 * $pad;
        $plotH = $h - 2 * $pad;

        // Grid lines
        $grid = Brush::color(Color::rgba(1.0, 1.0, 1.0, 0.08));
        for ($i = 0; $i < 4; ++$i) {
            $gy = $pad + ($plotH / 3) * $i;
            $ctx->strokeLine($pad, $gy, $w - $pad, $gy, $grid);
        }

        // Line path
        $points = [];
        foreach ($lineData as $i => $v) {
            $x = $pad + ($i / max(1, $count - 1)) * $plotW;
            $y = $pad + $plotH - ($v / $max) * $plotH;
            $points[] = [$x, $y];
        }

        // Fill area under line
        $fillPoints = $points;
        $fillPoints[] = [$pad + $plotW, $pad + $plotH];
        $fillPoints[] = [$pad, $pad + $plotH];
        $ctx->fillPolygon($fillPoints, Brush::color(Color::rgb(0x3B82F6, 0.15)));

        // Line stroke
        $lineColor = Brush::rgb(0x3B82F6);
        $sp = (new StrokeParams())->thickness(2.0);
        for ($i = 1; $i < count($points); ++$i) {
            $ctx->strokeLine($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $lineColor, $sp);
        }

        // Dots
        foreach ($points as [$px, $py]) {
            $ctx->fillCircle($px, $py, 3.0, Brush::rgb(0x3B82F6));
        }
    },
    background: 0x1E293B,
);

/** Mini bar chart canvas */
$barChartSpec = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h) use ($barData): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));

        $count = count($barData);
        $max = max($barData);
        $pad = 12.0;
        $plotW = $w - 2 * $pad;
        $plotH = $h - 2 * $pad;
        $barW = ($plotW / $count) * 0.6;
        $gap = ($plotW / $count) * 0.4;

        $colors = [0x3B82F6, 0x10B981, 0xF59E0B, 0xEF4444, 0x8B5CF6, 0x06B6D4, 0xEC4899, 0x84CC16];

        foreach ($barData as $i => $v) {
            $x = $pad + $i * ($barW + $gap) + $gap / 2;
            $barH = ($v / $max) * $plotH;
            $y = $pad + $plotH - $barH;
            $color = $colors[$i % count($colors)];
            $ctx->fillRect($x, $y, $barW, $barH, Brush::rgb($color));
        }
    },
    background: 0x1E293B,
);

/** Animated progress bar canvas */
$progressSpec = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h) use (&$progress): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x0F172A));

        $barH = 16.0;
        $y = ($h - $barH) / 2.0;
        $radius = $barH / 2.0;

        // Track
        $ctx->fillRect(0, $y, $w, $barH, Brush::rgb(0x1E293B));

        // Fill
        $fillW = $w * $progress;
        if ($fillW > 0) {
            // Gradient-like: blue to green
            $r = (int) (59 * (1 - $progress) + 16 * $progress);
            $g = (int) (130 * (1 - $progress) + 185 * $progress);
            $b = (int) (246 * (1 - $progress) + 129 * $progress);
            $color = ($r << 16) | ($g << 8) | $b;
            $ctx->fillRect(0, $y, $fillW, $barH, Brush::rgb($color));
        }

        // Percentage text
        $pct = (int) ($progress * 100);
        $font = new \Libui\Text\FontDescriptor('Helvetica', 11.0);
        $ctx->drawString("{$pct}%", $font, Color::rgb(0xE2E8F0), 0.0, $y + 1.0, $w, \Libui\Generated\Enum\DrawTextAlign::Center);
    },
);

// ═════════════════════════════════════════════════════════════════════════════
// LAYOUT
// ═════════════════════════════════════════════════════════════════════════════

$layout = LayoutNode::column(
    gap: 12,
    padding: 16,
)
    ->child(LayoutNode::leaf('title', new LabelSpec('CanvasSpec Demo', size: 20.0), height: 28.0))
    ->child(LayoutNode::leaf('spacer1', height: 4.0))
    ->child(LayoutNode::leaf('lineLabel', new LabelSpec('Mini Line Chart', size: 13.0, color: 'color.onSurface', opacity: 0.6), height: 18.0))
    ->child(LayoutNode::leaf('lineChart', $lineChartSpec, height: 160.0))
    ->child(LayoutNode::leaf('spacer2', height: 4.0))
    ->child(LayoutNode::leaf('barLabel', new LabelSpec('Mini Bar Chart', size: 13.0, color: 'color.onSurface', opacity: 0.6), height: 18.0))
    ->child(LayoutNode::leaf('barChart', $barChartSpec, height: 120.0))
    ->child(LayoutNode::leaf('spacer3', height: 4.0))
    ->child(LayoutNode::leaf('progressLabel', new LabelSpec('Animated Progress', size: 13.0, color: 'color.onSurface', opacity: 0.6), height: 18.0))
    ->child(LayoutNode::leaf('progressBar', $progressSpec, height: 40.0))
    ->child(LayoutNode::leaf('desc', new LabelSpec(
        'CanvasSpec embeds arbitrary DrawContext drawing into a Surface LayoutNode tree. '
        . 'Each canvas leaf receives (DrawContext, width, height) — draw anything.',
        size: 11.0, color: 'color.onSurface', opacity: 0.5,
    ), height: 36.0));

$surface = new Surface($layout);

// ═════════════════════════════════════════════════════════════════════════════
// PROGRESS ANIMATION
// ═════════════════════════════════════════════════════════════════════════════

Loop::repeat(50, function () use (&$progress, $surface): bool {
    $progress += 0.01;
    if ($progress > 1.0) {
        $progress = 0.0;
    }
    $surface->redraw();
    return true;
});

// ═════════════════════════════════════════════════════════════════════════════
// WINDOW
// ═════════════════════════════════════════════════════════════════════════════

$window = new Window('CanvasSpec Demo', 480, 520, false);
$window->setMargined(true);
$window->centered();
$window->setChild($surface->root());

App::new()
    ->window($window)
    ->onShouldQuit(fn (): bool => true)
    ->run();
