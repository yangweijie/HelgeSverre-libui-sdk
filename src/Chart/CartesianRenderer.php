<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Path;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawTextAlign;

/**
 * Shared base for cartesian charts (line / bar / scatter).
 *
 * Handles the boilerplate every axes chart needs — sizing the plotting area,
 * computing the Y scale, drawing grid lines, axis rules, tick labels and value
 * labels — then dispatches each series to {@see drawLineSeries()} or {@see
 * drawBarSeries()}. Because the base owns the coordinate system, mixed charts
 * (a bar dataset next to a line dataset on the same axes) render consistently.
 */
abstract class CartesianRenderer implements ChartRenderer
{
    public function render(DrawContext $ctx, Chart $chart, ChartView $view): void
    {
        $config = $chart->getConfig();
        $datasets = $chart->getDatasets();
        $display = $chart->getDisplayValues();
        $zoom = $chart->getZoom();

        $n = $this->categoryCount($datasets, $display);
        if ($n < 1) {
            $n = 1;
        }
        $values = $this->allNumbers($datasets);
        $yFull = Scale::forValues($values, $config->yZeroBased);

        // Publish the full + effective domains so interaction and axis painting
        // agree on coordinates.
        $zoom->setFull(0.0, (float) ($n - 1), $yFull->min, $yFull->max);
        $view->fullXMin = 0.0;
        $view->fullXMax = (float) ($n - 1);
        $view->fullYMin = $yFull->min;
        $view->fullYMax = $yFull->max;
        $view->xMin = $zoom->xMin;
        $view->xMax = $zoom->xMax;
        $view->yMin = $zoom->yMin;
        $view->yMax = $zoom->yMax;
        $view->categoryCount = $n;
        $view->labels = $chart->getLabels();
        foreach ($datasets as $i => $d) {
            $view->colors[$i] = $d->color ?? ($view->seriesColors[$i] ?? $config->colorAt($i));
            $view->legend[] = [$d->label, $view->colors[$i]];
        }
        $view->barHitboxes = [];
        $view->points = [];

        if ($config->showGrid || $config->showAxisY || $config->showAxisX) {
            $this->drawGridAndAxes($ctx, $chart, $view, $yFull, $n);
        }

        $this->drawSeries($ctx, $chart, $view, $n, $yFull);
    }

    /**
     * Paint the series. Subclasses loop the datasets and call the base
     * drawLineSeries()/drawBarSeries() helpers, choosing per dataset effective
     * type so mixed charts work.
     */
    abstract protected function drawSeries(
        DrawContext $ctx,
        Chart $chart,
        ChartView $view,
        int $n,
        Scale $yFull,
    ): void;

    protected function drawGridAndAxes(DrawContext $ctx, Chart $chart, ChartView $view, Scale $yFull, int $n): void
    {
        $config = $chart->getConfig();
        [$px, $py, $pw, $ph] = $view->plot;
        $left = $px;
        $right = $px + $pw;
        $top = $py;
        $bottom = $py + $ph;

        if ($config->showGrid || $config->showAxisY) {
            foreach ($yFull->ticks(5) as $v) {
                if ($v < $view->yMin - 1e-6 || $v > $view->yMax + 1e-6) {
                    continue;
                }
                $gy = $view->yToPx($v);
                if ($config->showGrid) {
                    $ctx->line($left, $gy, $right, $gy, Brush::rgb($config->gridColor), $config->gridLineWidth);
                }
                $this->text($ctx, $this->fmt($v), $view->fontSmall, $config->axisLabelColor,
                    $left - 8.0, $gy, 60.0, DrawTextAlign::Right);
            }
        }

        if ($config->showGrid || $config->showAxisX) {
            $step = max(1, (int) ceil(($view->xMax - $view->xMin) / 12.0));
            for ($i = 0; $i < $n; $i += $step) {
                if ($i < $view->xMin - 1e-6 || $i > $view->xMax + 1e-6) {
                    continue;
                }
                $gx = $view->xToPx((float) $i);
                if ($config->showGrid) {
                    $ctx->line($gx, $top, $gx, $bottom, Brush::rgb($config->gridColor), $config->gridLineWidth);
                }
                $label = $view->labels[$i] ?? (string) ($i + 1);
                $this->text($ctx, $this->fmtLabel($label), $view->fontSmall, $config->axisLabelColor,
                    $gx - 40.0, $bottom + 6.0, 80.0, DrawTextAlign::Center);
            }
        }

        if ($config->showAxisX) {
            $ctx->line($left, $bottom, $right, $bottom, Brush::rgb($config->axisColor), 1.0);
        }
        if ($config->showAxisY) {
            $ctx->line($left, $top, $left, $bottom, Brush::rgb($config->axisColor), 1.0);
        }
    }

    protected function drawLineSeries(
        DrawContext $ctx,
        Chart $chart,
        ChartView $view,
        Dataset $d,
        int $i,
        int $n,
    ): void {
        $config = $chart->getConfig();
        $color = $view->colors[$i] ?? $config->colorAt($i);
        $values = $chart->getDisplayValues()[$i] ?? [];
        $asScatter = ($d->type ?? $chart->getType()) === ChartType::Scatter;

        $pts = [];
        foreach ($values as $j => $v) {
            if ($v === null) {
                continue;
            }
            $pxv = $view->xToPx((float) $j);
            $pyv = $view->yToPx($v);
            $pts[] = [$pxv, $pyv, $v];
            $view->points[] = [$i, $j, $pxv, $pyv];
        }
        if ($pts === []) {
            return;
        }

        if ($d->fill && ! $asScatter) {
            $baseline = $view->yToPx(max(0.0, $view->yMin));
            $first = $pts[0];
            $last = $pts[count($pts) - 1];
            $ctx->fillPath(
                Brush::color(Color::rgb($color)->withAlpha(0.15)),
                static function (Path $p) use ($pts, $first, $last, $baseline): void {
                    $p->newFigure($first[0], $first[1]);
                    foreach ($pts as $pt) {
                        $p->lineTo($pt[0], $pt[1]);
                    }
                    $p->lineTo($last[0], $baseline);
                    $p->lineTo($first[0], $baseline);
                    $p->closeFigure();
                },
            );
        }

        if (! $asScatter) {
            $first = $pts[0];
            $ctx->strokePath(
                Brush::rgb($color),
                StrokeParams::solid($d->lineWidth),
                static function (Path $p) use ($pts, $first): void {
                    $p->newFigure($first[0], $first[1]);
                    foreach ($pts as $pt) {
                        $p->lineTo($pt[0], $pt[1]);
                    }
                },
            );
        }

        $radius = $d->pointRadius ?? 3.0;
        if ($d->showPoints || $asScatter) {
            foreach ($pts as $pt) {
                $ctx->fillCircle($pt[0], $pt[1], $radius, Brush::rgb($color));
            }
        }

        $hover = $chart->getHover();
        if ($hover !== null && ($hover['i'] ?? -1) === $i) {
            $hj = $hover['j'] ?? -1;
            foreach ($view->points as [$pi, $pj, $ppx, $ppy]) {
                if ($pi === $i && $pj === $hj) {
                    $ctx->strokeCircle($ppx, $ppy, $radius + 4.0, Brush::rgb(0xFFFFFF), StrokeParams::solid(2.0));
                    break;
                }
            }
        }

        foreach ($pts as $pt) {
            $this->maybeValueLabel($ctx, $chart, $view, $pt[0], $pt[1], $pt[2], $d->showValues);
        }
    }

    protected function drawBarSeries(
        DrawContext $ctx,
        Chart $chart,
        ChartView $view,
        Dataset $d,
        int $i,
        int $k,
        int $n,
    ): void {
        $config = $chart->getConfig();
        $color = $view->colors[$i] ?? $config->colorAt($i);
        $values = $chart->getDisplayValues()[$i] ?? [];
        [$px, $py, $pw, $ph] = $view->plot;

        $band = ($pw / (float) $n) * 0.72;
        $perBar = $k > 0 ? $band / (float) $k : $band;
        $groupOffset = ($i - ($k - 1) / 2.0) * $perBar;
        $barW = $perBar * 0.86;
        $baseline = $view->yToPx(max(0.0, $view->yMin));

        foreach ($values as $j => $v) {
            if ($v === null) {
                continue;
            }
            $cx = $view->xToPx((float) $j);
            $barX = $cx + $groupOffset - $barW / 2.0;
            $top = $view->yToPx($v);
            $h = $baseline - $top;
            if ($h < 0.5) {
                $h = 0.5;
                $top = $baseline - $h;
            }
            $ctx->fillRoundedRect($barX, $top, $barW, $h, min(6.0, $barW / 2.0), Brush::rgb($color));
            $view->barHitboxes[] = [$i, $j, $barX, $top, $barW, $h];
            $this->maybeValueLabel($ctx, $chart, $view, $cx, $top, $v, $d->showValues, $v < 0);
            if (($chart->getHover()['i'] ?? -1) === $i && ($chart->getHover()['j'] ?? -1) === $j) {
                $ctx->strokeRect($barX, $top, $barW, $h, Brush::rgb(0xFFFFFF), StrokeParams::solid(2.0));
            }
        }
    }

    protected function maybeValueLabel(
        DrawContext $ctx,
        Chart $chart,
        ChartView $view,
        float $xPx,
        float $yPx,
        float $value,
        ?bool $override,
        bool $below = false,
    ): void {
        $show = $override ?? $chart->getConfig()->showValues;
        if (! $show) {
            return;
        }
        $y = $below ? $yPx + 14.0 : $yPx - 14.0;
        $this->text($ctx, $this->fmt($value), $view->fontSmall, $chart->getConfig()->axisLabelColor,
            $xPx, $y, 60.0, DrawTextAlign::Center);
    }

    protected function text(
        DrawContext $ctx,
        string $s,
        \Libui\Text\FontDescriptor $font,
        int $color,
        float $x,
        float $y,
        ?float $w = null,
        DrawTextAlign $align = DrawTextAlign::Left,
    ): void {
        $ctx->drawString($s, $font, Color::rgb($color), $x, $y, $w, $align);
    }

    protected function fmt(float $v): string
    {
        if (abs($v - round($v)) < 1e-6) {
            return (string) (int) round($v);
        }

        return number_format($v, 1, '.', '');
    }

    protected function fmtLabel(string $s): string
    {
        return mb_strlen($s) > 10 ? mb_substr($s, 0, 9) . '…' : $s;
    }

    protected function categoryCount(array $datasets, array $display): int
    {
        $n = 0;
        foreach ($datasets as $d) {
            $n = max($n, count($d->data));
        }
        foreach ($display as $row) {
            $n = max($n, count($row));
        }

        return $n;
    }

    /** @return list<float> */
    protected function allNumbers(array $datasets): array
    {
        $out = [];
        foreach ($datasets as $d) {
            foreach ($d->numbers() as $v) {
                $out[] = $v;
            }
        }

        return $out;
    }
}
