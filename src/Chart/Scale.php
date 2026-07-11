<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * A linear value scale with "nice" rounded bounds and ticks, in the spirit of
 * Chart.js's auto axis. Used for the Y axis (and, where relevant, the X axis of
 * scatter charts). Categorical X axes are positioned by index, not by Scale.
 */
final class Scale
{
    public function __construct(
        public float $min,
        public float $max,
    ) {
    }

    /**
     * Build a scale that comfortably contains $values, optionally anchored at 0.
     */
    public static function forValues(array $values, bool $zeroBased = true): self
    {
        $values = array_filter($values, static fn ($v) => $v !== null && is_numeric($v));
        if ($values === []) {
            return new self(0.0, 1.0);
        }

        $mn = (float) min($values);
        $mx = (float) max($values);
        if ($zeroBased && $mn > 0) {
            $mn = 0.0;
        }
        if ($mn === $mx) {
            $mn -= 1.0;
            $mx += 1.0;
        }

        [$lo, $hi] = self::niceBounds($mn, $mx);

        return new self($lo, $hi);
    }

    /**
     * @return list<float> Rounded tick positions inside [min, max].
     */
    public function ticks(int $count = 5): array
    {
        $range = $this->max - $this->min;
        if ($range <= 0) {
            return [$this->min];
        }
        $step = self::niceNum($range / max(1, $count), true);
        $ticks = [];
        for ($v = ceil($this->min / $step) * $step; $v <= $this->max + 1e-9; $v += $step) {
            $ticks[] = round($v, 6);
        }

        return $ticks;
    }

    /** Map a value to a pixel Y inside [top, bottom] (y grows downward). */
    public function toPixel(float $value, float $top, float $bottom): float
    {
        $range = $this->max - $this->min;
        if ($range === 0.0) {
            return ($top + $bottom) / 2.0;
        }

        return $bottom - ($value - $this->min) / $range * ($bottom - $top);
    }

    /** Inverse of {@see toPixel()}. */
    public function fromPixel(float $py, float $top, float $bottom): float
    {
        $range = $this->max - $this->min;
        if ($range === 0.0) {
            return $this->min;
        }

        return $this->min + ($bottom - $py) / ($bottom - $top) * $range;
    }

    /**
     * Round the raw [min, max] out to a clean range with a little headroom so
     * the top line isn't glued to the data ceiling.
     *
     * @return array{float, float}
     */
    private static function niceBounds(float $min, float $max): array
    {
        $range = self::niceNum($max - $min, false);
        $step = self::niceNum($range / 5.0, true);
        $lo = floor($min / $step) * $step;
        $hi = ceil($max / $step) * $step;
        if ($lo === $hi) {
            $hi = $lo + $step;
        }

        return [$lo, $hi];
    }

    /**
     * Nearest 1/2/5 × 10ⁿ number. Mirrors the classic "nice numbers" algorithm.
     */
    private static function niceNum(float $x, bool $round): float
    {
        if ($x <= 0) {
            return 1.0;
        }
        $exp = floor(log10($x));
        $frac = $x / (10 ** $exp);
        if ($round) {
            $nf = $frac < 1.5 ? 1 : ($frac < 3 ? 2 : ($frac < 7 ? 5 : 10));
        } else {
            $nf = $frac <= 1 ? 1 : ($frac <= 2 ? 2 : ($frac <= 5 ? 5 : 10));
        }

        return $nf * (10 ** $exp);
    }
}
