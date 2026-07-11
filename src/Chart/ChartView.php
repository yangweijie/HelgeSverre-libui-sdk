<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Text\FontDescriptor;

/**
 * Mutable per-frame view shared between {@see Chart} and its renderers.
 *
 * {@see Chart} sizes the plot rectangle (accounting for title/legend); the
 * active renderer fills in the effective data domain (post-zoom), resolved
 * dataset colours, fonts and category labels, then draws. The pixel↔data
 * transforms are kept here so both rendering and pointer interaction agree on
 * the same coordinate mapping.
 */
final class ChartView
{
    /** [x, y, w, h] of the plotting rectangle in area pixels. */
    public array $plot = [0.0, 0.0, 1.0, 1.0];

    /** Effective (possibly zoomed) data domain. */
    public float $xMin = 0.0;
    public float $xMax = 1.0;
    public float $yMin = 0.0;
    public float $yMax = 1.0;

    /** Full (unzoomed) domain, for reference / reset. */
    public float $fullXMin = 0.0;
    public float $fullXMax = 1.0;
    public float $fullYMin = 0.0;
    public float $fullYMax = 1.0;

    /** @var list<int> dataset index => 0xRRGGBB */
    public array $colors = [];

    /**
     * Per-series colours for the current frame. Normally mirrors
     * {@see ChartConfig::colorAt()}, but during a series-recolour tween it holds
     * the {@see \Libui\Color::lerp}-interpolated values so renderers paint the
     * in-between shades without extra wiring. Null until the first draw.
     *
     * @var list<int>|null
     */
    public ?array $seriesColors = null;

    /** @var list<string> category labels for the X axis */
    public array $labels = [];

    /** Legend entries the host should draw: list of [label, 0xRRGGBB]. */
    public array $legend = [];

    /** @var list<array{int,int,float,float,float,float}> bar hitboxes [i,j,x,y,w,h] for hover */
    public array $barHitboxes = [];

    /** @var list<array{int,int,float,float}> line/scatter point hits [i,j,px,py] for hover */
    public array $points = [];

    /** Pie geometry for hover: [cx, cy] */
    public ?array $pieCenter = null;
    public float $pieRadius = 0.0;
    public float $pieInner = 0.0;
    /** @var list<array{a0:float,sweep:float,label:string,value:float,color:int,ox:float,oy:float}> */
    public array $pieSlices = [];

    public int $categoryCount = 0;

    public FontDescriptor $font;
    public FontDescriptor $fontSmall;

    public function __construct(ChartConfig $config)
    {
        $this->font = new FontDescriptor($config->fontFamily, $config->fontSize, \Libui\Generated\Enum\TextWeight::Medium);
        $this->fontSmall = new FontDescriptor($config->fontFamily, $config->axisFontSize);
    }

    public function isCartesian(): bool
    {
        return $this->xMax > $this->xMin;
    }

    public function xToPx(float $x): float
    {
        [$px, $py, $pw, $ph] = $this->plot;
        $range = ($this->xMax - $this->xMin) ?: 1.0;

        return $px + ($x - $this->xMin) / $range * $pw;
    }

    public function yToPx(float $y): float
    {
        [$px, $py, $pw, $ph] = $this->plot;
        $range = ($this->yMax - $this->yMin) ?: 1.0;

        return $py + $ph - ($y - $this->yMin) / $range * $ph;
    }

    public function pxToX(float $px): float
    {
        [$vx, $vy, $vw, $vh] = $this->plot;
        $range = ($this->xMax - $this->xMin) ?: 1.0;

        return $this->xMin + ($px - $vx) / ($vw ?: 1.0) * $range;
    }

    public function pxToY(float $py): float
    {
        [$vx, $vy, $vw, $vh] = $this->plot;
        $range = ($this->yMax - $this->yMin) ?: 1.0;

        return $this->yMin + ($vy + $vh - $py) / ($vh ?: 1.0) * $range;
    }
}
