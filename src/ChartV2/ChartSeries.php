<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\ChartV2;

/**
 * A single data series in a chart.
 *
 * @phpstan-type DataPoint array{label?: string, value: float|null}
 */
final class ChartSeries
{
    /** Series label (shown in legend) */
    public string $label = '';

    /** Series type: 'line' | 'bar' | 'area' */
    public string $type = 'line';

    /** Data points: array of [value] or [label, value] */
    public array $data = [];

    /** Colour (0xRRGGBB). Null = auto-assigned from palette. */
    public ?int $color = null;

    /** Line thickness (for line/area charts) */
    public float $lineWidth = 2.0;

    /** Fill opacity (for area charts, 0.0–1.0) */
    public float $fillOpacity = 0.15;

    /** Point radius (for line/scatter charts) */
    public float $pointRadius = 4.0;

    /** Show points (for line charts) */
    public bool $showPoints = true;

    /** Bar width (for bar charts, pixels) */
    public float $barWidth = 24.0;

    /** Bar gap (for bar charts, pixels) */
    public float $barGap = 4.0;

    /**
     * Create a series.
     *
     * @param list<float|null|array{label?:string,value:float}> $data
     */
    public function __construct(
        string $label = '',
        string $type = 'line',
        array $data = [],
        ?int $color = null,
    ) {
        $this->label = $label;
        $this->type = $type;
        $this->data = $data;
        $this->color = $color;
    }

    /** Add a single data point */
    public function addPoint(float|null $value): self
    {
        $this->data[] = ['value' => $value];
        return $this;
    }

    /** Add a named data point */
    public function addPointWithLabel(string $label, float|null $value): self
    {
        $this->data[] = ['label' => $label, 'value' => $value];
        return $this;
    }

    /** Set data array */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /** Set colour */
    public function setColor(int $hex): self
    {
        $this->color = $hex;
        return $this;
    }

    /** Set line width */
    public function setLineWidth(float $width): self
    {
        $this->lineWidth = $width;
        return $this;
    }

    /** Set fill opacity */
    public function setFillOpacity(float $opacity): self
    {
        $this->fillOpacity = max(0.0, min(1.0, $opacity));
        return $this;
    }

    /** Set point radius */
    public function setPointRadius(float $radius): self
    {
        $this->pointRadius = max(0.0, $radius);
        return $this;
    }

    /** Show/hide points */
    public function setShowPoints(bool $show): self
    {
        $this->showPoints = $show;
        return $this;
    }

    /** Set bar width */
    public function setBarWidth(float $width): self
    {
        $this->barWidth = max(0.0, $width);
        return $this;
    }

    /** Set bar gap */
    public function setBarGap(float $gap): self
    {
        $this->barGap = max(0.0, $gap);
        return $this;
    }

    /** Extract raw values (nulls become 0.0 for computation) */
    public function values(): array
    {
        $out = [];
        foreach ($this->data as $d) {
            $out[] = $d === null ? 0.0 : ($d['value'] ?? 0.0);
        }
        return $out;
    }

    /** Extract point labels (from data or use index) */
    public function pointLabels(): array
    {
        $out = [];
        foreach ($this->data as $i => $d) {
            $out[] = $d['label'] ?? null;
        }
        return $out;
    }
}
