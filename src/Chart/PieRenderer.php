<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Generated\Enum\DrawTextAlign;

/**
 * Renders pie and doughnut charts by self-drawing wedges (no library).
 *
 * Slice colours come from the chart palette indexed by slice; the legend is
 * populated with one entry per slice. Double-click toggles a radial "explode"
 * (see {@see Chart::togglePieExplode()}), the pie/doughnut equivalent of a zoom
 * gesture, since axes-based zoom does not apply to radial charts.
 */
final class PieRenderer implements ChartRenderer
{
    public function supports(ChartType $type): bool
    {
        return $type === ChartType::Pie || $type === ChartType::Doughnut;
    }

    public function render(DrawContext $ctx, Chart $chart, ChartView $view): void
    {
        $config = $chart->getConfig();
        $datasets = $chart->getDatasets();
        if ($datasets === []) {
            return;
        }

        // First dataset supplies the slice magnitudes; labels come from the chart.
        $sliceData = $chart->getDisplayValues()[0] ?? [];
        $labels = $chart->getLabels();

        $slices = [];
        $total = 0.0;
        foreach ($sliceData as $j => $v) {
            if ($v === null || $v <= 0) {
                continue;
            }
            $color = $view->seriesColors[$j] ?? $config->colorAt($j);
            $label = $labels[$j] ?? ('Slice ' . ($j + 1));
            $slices[] = ['value' => (float) $v, 'color' => $color, 'label' => $label];
            $total += (float) $v;
            $view->legend[] = [$label, $color];
        }
        if ($total <= 0) {
            return;
        }

        [$px, $py, $pw, $ph] = $view->plot;
        $cx = $px + $pw / 2.0;
        $cy = $py + $ph / 2.0;
        $radius = min($pw, $ph) / 2.0 * 0.86;
        $isDoughnut = $chart->getType() === ChartType::Doughnut;
        $innerR = $isDoughnut ? $radius * 0.56 : 0.0;

        $explode = $chart->isPieExploded() ? $radius * 0.09 : 0.0;
        $start = -M_PI / 2; // 12 o'clock

        $view->pieCenter = [$cx, $cy];
        $view->pieRadius = $radius;
        $view->pieInner = $innerR;
        $view->pieSlices = [];

        $hoverSlice = $chart->getHover()['slice'] ?? -1;

        foreach ($slices as $idx => $s) {
            $sweep = $s['value'] / $total * 2.0 * M_PI;
            $mid = $start + $sweep / 2.0;
            $ox = $explode * cos($mid);
            $oy = $explode * sin($mid);

            $ctx->fillArc($cx + $ox, $cy + $oy, $radius, $start, $sweep, Brush::rgb($s['color']));

            if ($idx === $hoverSlice) {
                $ctx->fillArc($cx + $ox, $cy + $oy, $radius, $start, $sweep, Brush::rgb(0xFFFFFF, 0.28));
            }

            if ($config->showValues) {
                $lr = $isDoughnut ? ($innerR + $radius) / 2.0 : $radius * 0.62;
                $lx = $cx + $ox + $lr * cos($mid);
                $ly = $cy + $oy + $lr * sin($mid);
                $pct = round($s['value'] / $total * 100.0) . '%';
                $ctx->drawString($pct, $view->fontSmall, Color::rgb(0xFFFFFF),
                    $lx, $ly, 60.0, DrawTextAlign::Center);
            }

            $view->pieSlices[] = [
                'a0' => $start,
                'sweep' => $sweep,
                'label' => $s['label'],
                'value' => $s['value'],
                'color' => $s['color'],
                'ox' => $ox,
                'oy' => $oy,
            ];

            $start += $sweep;
        }

        if ($isDoughnut) {
            $ctx->fillCircle($cx, $cy, $innerR, Brush::rgb($config->plotBackground));
        }
    }
}
