<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * A single series of data.
 *
 * For cartesian charts (line/bar/scatter) {@see Dataset::$data} is a list of
 * numbers. For pie/doughnut charts the same field holds the slice magnitudes.
 *
 * Every visual aspect is overridable per-series so a chart can mix styles:
 *
 *     new Dataset('Revenue', [12, 19, 8], 0x6366F1, fill: true);
 *     new Dataset('Target',  [10, 15, 12], 0x10B981, type: ChartType::Line);
 */
final class Dataset
{
    /**
     * @param string            $label     Legend / series name.
     * @param list<float|null>  $data      Values (null = gap, rendered as a break).
     * @param int|null          $color     0xRRGGBB override; null => palette colour.
     * @param ChartType|null    $type      Per-series override (mixed charts).
     * @param bool              $fill      Line: fill area under the curve.
     * @param float             $lineWidth Line stroke thickness.
     * @param bool              $showPoints Draw markers at each point.
     * @param bool|null         $showValues Show value labels (null => chart default).
     * @param float|null        $pointRadius Marker radius in px.
     */
    public function __construct(
        public string $label,
        public array $data,
        public ?int $color = null,
        public ?ChartType $type = null,
        public bool $fill = false,
        public float $lineWidth = 2.0,
        public bool $showPoints = true,
        public ?bool $showValues = null,
        public ?float $pointRadius = null,
    ) {
    }

    /** Numeric values only (nulls dropped), for scale computation. */
    public function numbers(): array
    {
        $out = [];
        foreach ($this->data as $v) {
            if ($v !== null && is_numeric($v)) {
                $out[] = (float) $v;
            }
        }

        return $out;
    }
}
