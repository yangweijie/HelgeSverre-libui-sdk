<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\ChartV2;

/**
 * Tick lattice and domain computation — pure functions, no drawing.
 * Mirrors the Zig web-native chart `tickLattice` / `niceNumber` logic.
 */
final class Scale
{
    /**
     * Generate "nice" axis ticks.
     *
     * @return array{min:float, max:float, ticks:array<float>}
     */
    public static function niceTicks(float $min, float $max, int $count): array
    {
        if ($count <= 0) {
            return ['min' => $min, 'max' => $max, 'ticks' => []];
        }

        $rawRange = $max - $min;
        if ($rawRange === 0.0) {
            $max = $min + 1.0;
            $rawRange = 1.0;
        }

        $rawStep = $rawRange / ($count - 1);
        $magnitude = floor(log10($rawStep));
        $scaledStep = $rawStep / (10 ** $magnitude);

        // Choose a "nice" step: 1, 2, 5, 10, 20, 50, 100...
        $niceStep = match (true) {
            $scaledStep < 1.5 => 1.0,
            $scaledStep < 3.5 => 2.0,
            $scaledStep < 7.5 => 5.0,
            default => 10.0,
        };

        $step = $niceStep * (10 ** $magnitude);

        // Round min down to a nice boundary
        $niceMin = floor($min / $step) * $step;
        $niceMax = ceil($max / $step) * $step;

        $ticks = [];
        for ($t = $niceMin; $t <= $niceMax + 1e-9; $t += $step) {
            $ticks[] = round($t, 10); // avoid floating-point noise
        }

        // Ensure we have at least `count` ticks
        if (count($ticks) < $count) {
            $extra = $count - count($ticks);
            for ($i = 0; $i < $extra; $i++) {
                $ticks[] = $ticks[count($ticks) - 1] + $step;
            }
        }

        return ['min' => $niceMin, 'max' => $niceMax, 'ticks' => $ticks];
    }

    /**
     * Compute the effective data domain from series values.
     *
     * @param list<ChartSeries> $series
     * @return array{float,float} [min, max]
     */
    public static function computeDomain(array $series): array
    {
        $min = PHP_FLOAT_MAX;
        $max = PHP_FLOAT_MIN;

        foreach ($series as $s) {
            foreach ($s->values() as $v) {
                if ($v !== null) {
                    $min = min($min, $v);
                    $max = max($max, $v);
                }
            }
        }

        if ($min === PHP_FLOAT_MAX) {
            $min = 0.0;
            $max = 1.0;
        }

        // Add 10% padding
        $pad = ($max - $min) * 0.1;
        $min -= $pad;
        $max += $pad;

        if ($min === $max) {
            $min -= 1.0;
            $max += 1.0;
        }

        return [$min, $max];
    }

    /**
     * Map a data value to a pixel position within a plot range.
     */
    public static function mapValue(float $value, float $dataMin, float $dataMax, float $pixelMin, float $pixelMax): float
    {
        $dataRange = $dataMax - $dataMin;
        if ($dataRange === 0.0) {
            return $pixelMin;
        }
        return $pixelMin + ($value - $dataMin) / $dataRange * ($pixelMax - $pixelMin);
    }

    /**
     * Inverse: map a pixel position back to a data value.
     */
    public static function unmapValue(float $pixel, float $dataMin, float $dataMax, float $pixelMin, float $pixelMax): float
    {
        $pixelRange = $pixelMax - $pixelMin;
        if ($pixelRange === 0.0) {
            return $dataMin;
        }
        return $dataMin + ($pixel - $pixelMin) / $pixelRange * ($dataMax - $dataMin);
    }

    /**
     * Format a tick value for display.
     */
    public static function formatTick(float $value, int $precision = 2): string
    {
        if (abs($value) < 1e-6) {
            return '0';
        }

        $abs = abs($value);
        if ($abs >= 1e6) {
            return number_format($value, 0, '.', ',');
        }

        if ($abs >= 1000) {
            return number_format(round($value, 1), 1, '.', ',');
        }

        if ($abs < 0.01) {
            return number_format($value, 4, '.', '');
        }

        return number_format(round($value, $precision), $precision, '.', '');
    }
}
