<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Draw\DrawContext;

/**
 * Renders bar charts (and bar series inside mixed charts).
 *
 * A dataset whose {@see Dataset::$type} is Line/Scatter is delegated to the
 * shared line routine, so a bar chart can overlay a trend line.
 */
final class BarRenderer extends CartesianRenderer
{
    public function supports(ChartType $type): bool
    {
        return $type === ChartType::Bar;
    }

    protected function drawSeries(DrawContext $ctx, Chart $chart, ChartView $view, int $n, Scale $yFull): void
    {
        $datasets = $chart->getDatasets();
        $k = count($datasets);
        foreach ($datasets as $i => $d) {
            $effective = $d->type ?? $chart->getType();
            if ($effective === ChartType::Bar) {
                $this->drawBarSeries($ctx, $chart, $view, $d, $i, $k, $n);
            } else {
                $this->drawLineSeries($ctx, $chart, $view, $d, $i, $n);
            }
        }
    }
}
