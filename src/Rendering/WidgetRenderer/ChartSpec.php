<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\ChartV2\ChartData;
use Yangweijie\Ui2\ChartV2\ChartSeries;

/**
 * Visual state for a chart component.
 *
 * @property-read ChartData    $data         Chart data model
 * @property-read string       $type         Chart type
 * @property-read bool         $showGrid     Show grid lines
 * @property-read bool         $showAxes     Show axes
 * @property-read bool         $showTicks    Show tick labels
 * @property-read bool         $showLegend   Show legend
 * @property-read bool         $showTitle    Show title
 * @property-read array<int>   $palette      Colour palette
 * @property-read array<float> $padding      [top, right, bottom, left]
 * @property-read float        $animationDuration Animation duration in ms
 * @property-read bool         $animate      Animate data updates
 */
final class ChartSpec extends WidgetSpec
{
    public function __construct(
        public readonly ChartData $data,
        public readonly array $palette = [
            0x3B82F6, // blue
            0x10B981, // emerald
            0xF59E0B, // amber
            0xEF4444, // red
            0x8B5CF6, // violet
            0x06B6D4, // cyan
            0x84CC16, // lime
            0xEC4899, // pink
        ],
        public readonly array $padding = [32.0, 16.0, 48.0, 64.0],
        public readonly bool $showGrid = true,
        public readonly bool $showAxes = true,
        public readonly bool $showTicks = true,
        public readonly bool $showLegend = true,
        public readonly bool $showTitle = true,
        public readonly float $animationDuration = 400.0,
        public readonly bool $animate = true,
    ) {
    }

    public function type(): string
    {
        return 'chart';
    }
}
