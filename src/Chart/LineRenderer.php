<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Draw\DrawContext;

/**
 * Renders line and scatter charts (and line series inside mixed charts).
 *
 * A dataset whose {@see Dataset::$type} is Bar is delegated to the shared bar
 * routine, so a line chart can host a bar series on the same axes.
 */
final class LineRenderer extends CartesianRenderer
{
    public function supports(ChartType $type): bool
    {
        return $type === ChartType::Line || $type === ChartType::Scatter;
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
