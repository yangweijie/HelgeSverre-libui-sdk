<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Draw\DrawContext;

/**
 * A self-drawn chart renderer. Renderers receive the owning {@see Chart} (for
 * datasets, animated display values, zoom state and config) and a {@see
 * ChartView} they populate and paint into. Add a new chart kind by implementing
 * this interface and registering it in {@see RendererFactory} — no changes to
 * the core component required.
 */
interface ChartRenderer
{
    /** Whether this renderer handles the given chart kind. */
    public function supports(ChartType $type): bool;

    /**
     * Paint the chart body and fill $view with the effective domain, colours,
     * fonts and labels so the host can draw axes, legend and handle interaction.
     */
    public function render(DrawContext $ctx, Chart $chart, ChartView $view): void;
}
