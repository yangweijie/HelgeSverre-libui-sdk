<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * Viewport state for cartesian charts.
 *
 * Stores the *effective* visible domain [xMin,xMax]×[yMin,yMax] in data units.
 * When not zoomed it equals the full domain. {@see Chart} converts the pointer
 * position to data coordinates (via {@see ChartView}) and calls {@see zoomAt()}
 * or {@see pan()} — the latter two keep the anchor point visually fixed, which
 * is exactly the behaviour users expect from pinch / drag-zoom.
 *
 * The underlying libui Area only forwards mouse/key events (no wheel/touch),
 * so "pinch" is emulated by a Shift+drag gesture and "double-click" zoom uses
 * the native click-count flag — see {@see Chart::mouse()}.
 */
final class ZoomState
{
    public float $xMin;
    public float $xMax;
    public float $yMin;
    public float $yMax;
    public bool $active = false;

    private float $fullXMin = 0.0;
    private float $fullXMax = 1.0;
    private float $fullYMin = 0.0;
    private float $fullYMax = 1.0;
    private float $maxZoom = 16.0;

    public function __construct(float $maxZoom = 16.0)
    {
        $this->maxZoom = $maxZoom;
        $this->xMin = $this->fullXMin;
        $this->xMax = $this->fullXMax;
        $this->yMin = $this->fullYMin;
        $this->yMax = $this->fullYMax;
    }

    /** Register the full (unzoomed) domain; adopts it when not zoomed. */
    public function setFull(float $xMin, float $xMax, float $yMin, float $yMax): void
    {
        $this->fullXMin = $xMin;
        $this->fullXMax = $xMax;
        $this->fullYMin = $yMin;
        $this->fullYMax = $yMax;
        if (! $this->active) {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->xMin = $this->fullXMin;
        $this->xMax = $this->fullXMax;
        $this->yMin = $this->fullYMin;
        $this->yMax = $this->fullYMax;
        $this->active = false;
    }

    /**
     * Restore a previously captured domain (used as the reference point while a
     * pinch/pan gesture is in progress, so each move recomputes from the start
     * instead of compounding onto the already-transformed domain). Does not
     * recompute $active — callers decide that via pan()/zoomAt().
     */
    public function setDomain(float $xMin, float $xMax, float $yMin, float $yMax): void
    {
        $this->xMin = $xMin;
        $this->xMax = $xMax;
        $this->yMin = $yMin;
        $this->yMax = $yMax;
    }

    public function isNearFull(): bool
    {
        return ! $this->active;
    }

    /**
     * Zoom by $factor (>1 in, <1 out) keeping data point ($ax,$ay) fixed on
     * screen. Used by double-click and Shift-drag pinch emulation.
     */
    public function zoomAt(float $factor, float $ax, float $ay): void
    {
        $factor = max(1.0 / $this->maxZoom, min($this->maxZoom, $factor));
        $wx = ($this->xMax - $this->xMin) ?: 1.0;
        $wy = ($this->yMax - $this->yMin) ?: 1.0;
        $nwx = $wx / $factor;
        $nwy = $wy / $factor;
        $fx = ($ax - $this->xMin) / $wx;
        $fy = ($ay - $this->yMin) / $wy;

        $this->xMin = $ax - $fx * $nwx;
        $this->xMax = $ax + (1.0 - $fx) * $nwx;
        $this->yMin = $ay - $fy * $nwy;
        $this->yMax = $ay + (1.0 - $fy) * $nwy;
        $this->active = true;
        $this->clamp();
    }

    /**
     * Pan by a fraction of the current viewport. $dxFrac>0 drags content right
     * (reveals smaller X); $dyFrac>0 drags content down (reveals larger Y).
     */
    public function pan(float $dxFrac, float $dyFrac): void
    {
        $wx = ($this->xMax - $this->xMin) ?: 1.0;
        $wy = ($this->yMax - $this->yMin) ?: 1.0;
        $this->xMin -= $dxFrac * $wx;
        $this->xMax -= $dxFrac * $wx;
        $this->yMin += $dyFrac * $wy;
        $this->yMax += $dyFrac * $wy;
        $this->active = true;
        $this->clamp();
    }

    /**
     * Jump straight to an explicit data domain (used by the drag-to-select
     * box zoom). Marks the viewport active and clamps to the full domain.
     */
    public function zoomTo(float $xMin, float $xMax, float $yMin, float $yMax): void
    {
        $this->xMin = $xMin;
        $this->xMax = $xMax;
        $this->yMin = $yMin;
        $this->yMax = $yMax;
        $this->active = true;
        $this->clamp();
    }

    private function clamp(): void
    {
        $wx = $this->xMax - $this->xMin;
        $wy = $this->yMax - $this->yMin;
        $fwx = $this->fullXMax - $this->fullXMin;
        $fwy = $this->fullYMax - $this->fullYMin;

        if ($wx >= $fwx) {
            $this->xMin = $this->fullXMin;
            $this->xMax = $this->fullXMax;
        } else {
            if ($this->xMin < $this->fullXMin) {
                $this->xMax += $this->fullXMin - $this->xMin;
                $this->xMin = $this->fullXMin;
            }
            if ($this->xMax > $this->fullXMax) {
                $this->xMin -= $this->xMax - $this->fullXMax;
                $this->xMax = $this->fullXMax;
            }
        }

        if ($wy >= $fwy) {
            $this->yMin = $this->fullYMin;
            $this->yMax = $this->fullYMax;
        } else {
            if ($this->yMin < $this->fullYMin) {
                $this->yMax += $this->fullYMin - $this->yMin;
                $this->yMin = $this->fullYMin;
            }
            if ($this->yMax > $this->fullYMax) {
                $this->yMin -= $this->yMax - $this->fullYMax;
                $this->yMax = $this->fullYMax;
            }
        }

        $this->active = abs($this->xMin - $this->fullXMin) > 1e-6
            || abs($this->xMax - $this->fullXMax) > 1e-6
            || abs($this->yMin - $this->fullYMin) > 1e-6
            || abs($this->yMax - $this->fullYMax) > 1e-6;
    }
}
