<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\ChartV2;

/**
 * Pure data model for a chart — mirroring the Zig web-native chart.zig
 * `ChartSeries`/`ChartData` pattern. No drawing logic, no Area dependency.
 *
 * Supports: line, bar, area, pie, doughnut, scatter.
 */
final class ChartData
{
    /** @var list<ChartSeries> */
    public array $series = [];

    /** @var list<string> category labels (X-axis or pie slices) */
    public array $labels = [];

    /** Chart type: 'line' | 'bar' | 'area' | 'pie' | 'doughnut' | 'scatter' */
    public string $type = 'line';

    /** Title displayed above the chart */
    public string $title = '';

    /** X-axis label */
    public string $xLabel = '';

    /** Y-axis label */
    public string $yLabel = '';

    /** Legend position: 'top' | 'right' | 'bottom' | 'none' */
    public string $legendPosition = 'right';

    /** Show legend */
    public bool $showLegend = true;

    /** Show title */
    public bool $showTitle = true;

    /** Padding [top, right, bottom, left] */
    public array $padding = [32.0, 16.0, 48.0, 64.0];

    /** Font family */
    public string $fontFamily = 'Inter, -apple-system, sans-serif';

    /** Light theme colours */
    public array $lightTheme = [
        'background' => 0xF8FAFC,
        'grid'       => 0xE2E8F0,
        'axis'       => 0x64748B,
        'text'       => 0x1E293B,
        'legend'     => 0x475569,
        'tooltipBg'  => 0x1E293B,
        'tooltipText'=> 0xF8FAFC,
    ];

    /** Dark theme colours */
    public array $darkTheme = [
        'background' => 0x0F172A,
        'grid'       => 0x1E293B,
        'axis'       => 0x64748B,
        'text'       => 0xF1F5F9,
        'legend'     => 0x94A3B8,
        'tooltipBg'  => 0x334155,
        'tooltipText'=> 0xF8FAFC,
    ];

    /** Current theme: 'light' | 'dark' */
    public string $theme = 'light';

    /** Custom colour palette (0xRRGGBB ints) */
    public ?array $palette = null;

    /** Animation duration in ms */
    public int $animationDuration = 400;

    /** Animate data updates */
    public bool $animate = true;

    /** Max zoom factor */
    public float $maxZoom = 5.0;

    /** Show grid lines */
    public bool $showGrid = true;

    /** Show axes */
    public bool $showAxes = true;

    /** Show ticks */
    public bool $showTicks = true;

    /** Show value labels on bars */
    public bool $showValueLabels = false;

    /** Number of Y-axis ticks */
    public int $yTicks = 5;

    /** Number of X-axis ticks */
    public int $xTicks = 5;

    /**
     * Create a chart data object.
     *
     * @param list<ChartSeries> $series
     * @param list<string>      $labels
     */
    public static function create(array $series = [], array $labels = []): self
    {
        $chart = new self();
        $chart->series = $series;
        $chart->labels = $labels;
        return $chart;
    }

    /** Add a series */
    public function addSeries(ChartSeries $series): self
    {
        $this->series[] = $series;
        return $this;
    }

    /** Set category labels */
    public function setLabels(array $labels): self
    {
        $this->labels = $labels;
        return $this;
    }

    /** Set chart type */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /** Set title */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /** Set X-axis label */
    public function setXLabel(string $label): self
    {
        $this->xLabel = $label;
        return $this;
    }

    /** Set Y-axis label */
    public function setYLabel(string $label): self
    {
        $this->yLabel = $label;
        return $this;
    }

    /** Set legend position */
    public function setLegendPosition(string $position): self
    {
        $this->legendPosition = $position;
        return $this;
    }

    /** Set palette colours */
    public function palette(int ...$hex): self
    {
        $this->palette = $hex;
        return $this;
    }

    /** Apply a theme preset */
    public function applyTheme(string $name): self
    {
        $this->theme = $name;
        $this->palette = match ($name) {
            'dark' => $this->darkTheme,
            default => $this->lightTheme,
        };
        return $this;
    }

    /** Current theme colours */
    public function themeColors(): array
    {
        return match ($this->theme) {
            'dark' => $this->darkTheme,
            default => $this->lightTheme,
        };
    }
}
