<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\StrokeParams;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Generated\Enum\TextWeight;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;

/**
 * A self-drawn, interactive chart component built on a libui Area.
 *
 * Feature parity with the brief:
 *  - Line / Bar / Pie / Doughnut / Scatter (mixed series supported).
 *  - 100% Area-canvas drawing — no third-party chart library.
 *  - Gesture zoom: double-click zooms in (Ctrl/Shift+double-click resets),
 *    Shift+drag emulates pinch, plain drag pans; +/-/0 keys also zoom.
 *  - Animated data updates with an ease-out cubic tween (loop-driven in the
 *    GUI; seekable for tests).
 *  - Title, grid, axes, ticks, legend and value labels.
 *  - Everything is configurable via {@see ChartConfig} and extensible via new
 *    {@see ChartRenderer} implementations.
 *
 * The component is headless-safe: without a bound Area it still computes data
 * and applies updates immediately, so it can be unit-tested without a display.
 */
final class Chart extends AreaDelegate
{
    private ChartType $type;
    private ChartConfig $config;
    /** @var list<Dataset> */
    private array $datasets = [];
    /** @var list<array<float|null>> currently displayed (possibly animated) values */
    private array $displayValues = [];
    /** @var list<string> */
    private array $labels = [];

    private ZoomState $zoom;
    private Animator $animator;
    /** Separate animator for theme colour-tweens so a data tween is never clobbered. */
    private Animator $themeAnimator;
    /** Separate animator for series-colour tweens so a data/theme tween is never clobbered. */
    private Animator $colorAnimator;
    /** Currently displayed per-series colours (0xRRGGBB); tweened on recolor(). */
    private array $displayColors = [];
    /** True while a series-colour tween is mid-flight. */
    private bool $colorAnimating = false;
    private bool $pieExploded = false;

    /** Area captured on bind (parent's $area is private, so we keep our own). */
    private ?Area $boundArea = null;
    private ?ChartView $view = null;

    private bool $dragging = false;
    private string $dragMode = 'pan';
    /** @var array{float,float} */
    private array $dragStartPx = [0.0, 0.0];
    /** @var array{float,float,float,float} */
    private array $dragStartDomain = [0.0, 0.0, 0.0, 0.0];
    /** @var array{float,float} */
    private array $dragAnchor = [0.0, 0.0];
    /** @var array{float,float,float,float}|null box-zoom rectangle in area px while dragging */
    private ?array $dragBox = null;

    /** @var array{i:int,j:int}|array{slice:int}|null hovered element (cartesian or pie) */
    private ?array $hover = null;
    /** @var array{float,float} last pointer position for tooltip placement */
    private array $hoverPx = [0.0, 0.0];

    /**
     * @param list<Dataset> $datasets Initial series (optional).
     */
    public function __construct(ChartType $type, ?ChartConfig $config = null, array $datasets = [])
    {
        $this->type = $type;
        $this->config = $config ?? new ChartConfig();
        $this->zoom = new ZoomState($this->config->maxZoom);
        $this->animator = new Animator();
        $this->themeAnimator = new Animator();
        $this->colorAnimator = new Animator();
        $this->datasets = array_values($datasets);
        $this->syncDisplayImmediate();
        $this->displayColors = $this->config->seriesPalette($this->seriesColorCount());
    }

    /** AreaDelegate bind hook — also stash the area for redraws. */
    public function bindArea(Area $area): void
    {
        $this->boundArea = $area;
        parent::bindArea($area);
    }

    /* ============================ Public API ============================ */

    public function getType(): ChartType
    {
        return $this->type;
    }

    public function getConfig(): ChartConfig
    {
        return $this->config;
    }

    /** @return list<Dataset> */
    public function getDatasets(): array
    {
        return $this->datasets;
    }

    /** @return list<array<float|null>> */
    public function getDisplayValues(): array
    {
        return $this->displayValues;
    }

    /** @return list<string> */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getZoom(): ZoomState
    {
        return $this->zoom;
    }

    public function getAnimator(): Animator
    {
        return $this->animator;
    }

    /** Currently hovered element, or null. Cartesian → ['i','j']; pie → ['slice']. */
    public function getHover(): ?array
    {
        return $this->hover;
    }

    public function isPieExploded(): bool
    {
        return $this->pieExploded;
    }

    /** Fluent config mutator. */
    public function configure(callable $fn): self
    {
        $fn($this->config);

        return $this;
    }

    /** @param list<string> $labels Category labels for the X axis / pie slices. */
    public function setLabels(array $labels): self
    {
        $this->labels = array_values($labels);

        return $this;
    }

    public function setType(ChartType $type): self
    {
        $this->type = $type;
        $this->zoom->reset();
        $this->dragBox = null;
        $this->hover = null;
        $this->redraw();

        return $this;
    }

    /**
     * Replace the data. With $animate (or the config default) and a live Area,
     * the values tween from the previous state; otherwise they snap immediately
     * (also the headless path, since no event loop pumps the tween).
     *
     * @param list<Dataset> $datasets
     */
    public function setData(array $datasets, ?bool $animate = null): self
    {
        $datasets = array_values($datasets);
        $this->datasets = $datasets;
        $this->zoom->reset();
        $this->dragBox = null;
        $this->hover = null;

        $animate = $animate ?? $this->config->animate;
        if ($animate && $this->boundArea !== null) {
            $from = $this->displayValues;
            $to = [];
            foreach ($datasets as $d) {
                $row = [];
                foreach ($d->data as $v) {
                    $row[] = $v === null ? 0.0 : (float) $v;
                }
                $to[] = $row;
            }
            $this->animator->animate(
                $from,
                $to,
                $this->config->animationDuration,
                function (array $vals): void {
                    $this->displayValues = $vals;
                    $this->redraw();
                },
                static function (): void {
                },
            );
        } else {
            $this->syncDisplayImmediate();
            $this->redraw();
        }

        return $this;
    }

    public function resetZoom(): self
    {
        $this->zoom->reset();
        $this->dragBox = null;
        $this->hover = null;
        $this->redraw();

        return $this;
    }

    /**
     * Switch the active colour theme (light / dark). With $animate (or the config
     * default) and a live Area, the themed colours tween smoothly to the new
     * preset via {@see Color::lerp}; otherwise they snap immediately (also the
     * headless path, since no event loop pumps the tween).
     */
    public function setTheme(string $name, ?bool $animate = null): self
    {
        $animate = $animate ?? $this->config->animate;
        if (! $animate || $this->boundArea === null) {
            $this->config->applyTheme($name);
            $this->hover = null;
            $this->redraw();

            return $this;
        }

        $this->animateTheme($name);

        return $this;
    }

    /** The animator driving theme colour-tweens (seekable in tests). */
    public function getThemeAnimator(): Animator
    {
        return $this->themeAnimator;
    }

    public function togglePieExplode(): self
    {
        $this->pieExploded = ! $this->pieExploded;
        $this->redraw();

        return $this;
    }

    /**
     * Tween every themed colour field from its current value to the target
     * preset using the shared {@see Animator} (ease-out cubic) and
     * {@see ChartConfig::interpolateTheme()} (which calls {@see Color::lerp}).
     */
    private function animateTheme(string $name): void
    {
        $known = array_key_exists($name, ChartConfig::THEMES);
        $targetName = $known ? $name : 'light';

        $fromRows = $this->themeColorsToRows($this->currentThemeColors());
        $toRows = $this->themeColorsToRows(ChartConfig::THEMES[$targetName]);

        $this->themeAnimator->animate(
            $fromRows,
            $toRows,
            $this->config->animationDuration,
            function (array $rows) use ($targetName): void {
                $this->applyThemeRows($rows);
            },
            function () use ($targetName): void {
                // lock in exact target colours + record the resolved theme name
                $this->config->applyThemeColors(ChartConfig::THEMES[$targetName]);
                $this->config->theme = $targetName;
                $this->redraw();
            },
        );
    }

    /** @return array<string,int> */
    private function currentThemeColors(): array
    {
        $out = [];
        foreach (ChartConfig::THEMED_FIELDS as $field) {
            $out[$field] = $this->config->{$field};
        }

        return $out;
    }

    /** Map a name→int colour map into the Animator's list-of-[r,g,b]-rows shape. */
    private function themeColorsToRows(array $colors): array
    {
        $rows = [];
        foreach (ChartConfig::THEMED_FIELDS as $field) {
            $c = Color::rgb($colors[$field] ?? 0xFFFFFF);
            $rows[] = [$c->r, $c->g, $c->b];
        }

        return $rows;
    }

    /** Apply a list of [r,g,b] rows (from the tween) back onto the config fields. */
    private function applyThemeRows(array $rows): void
    {
        $i = 0;
        foreach (ChartConfig::THEMED_FIELDS as $field) {
            [$r, $g, $b] = $rows[$i];
            $this->config->{$field} = Color::rgb255(
                (int) round(max(0.0, min(1.0, $r)) * 255),
                (int) round(max(0.0, min(1.0, $g)) * 255),
                (int) round(max(0.0, min(1.0, $b)) * 255),
            )->toHex();
            $i++;
        }
        $this->redraw();
    }

    /**
     * Number of distinct series/slice colours the chart needs right now. For
     * cartesian charts that's the dataset count; for pie/doughnut it's the
     * larger of the dataset count and the first dataset's category count, so
     * every slice gets its own colour slot.
     */
    private function seriesColorCount(): int
    {
        $n = count($this->datasets);
        if (! $this->type->isCartesian()) {
            $n = max($n, count($this->displayValues[0] ?? []));
        }

        return max(1, $n);
    }

    /**
     * Per-series colours for the current frame. While a recolour tween runs we
     * return the in-flight {@see self::$displayColors}; otherwise we resolve
     * from the config palette (and keep {@see self::$displayColors} in sync so
     * the next tween always starts from the true current colour).
     *
     * @return list<int>
     */
    private function currentSeriesColors(): array
    {
        $n = $this->seriesColorCount();
        if ($this->colorAnimating) {
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[$i] = $this->displayColors[$i] ?? $this->config->colorAt($i);
            }

            return $out;
        }
        $out = $this->config->seriesPalette($n);
        $this->displayColors = $out;

        return $out;
    }

    /**
     * Re-assign the categorical palette and, when bound + animated, tween every
     * series colour from its current shade to the new one with {@see Color::lerp}
     * (reusing the shared ease-out cubic {@see Animator}). Headless (no Area)
     * snaps immediately, mirroring setData/setTheme.
     *
     * @param int ...$hex 0xRRGGBB values; omit to revert to the named palette.
     */
    public function recolor(int ...$hex): self
    {
        $from = $this->displayColors;
        if ($hex === []) {
            $this->config->customPalette = null;
        } else {
            $this->config->colors(...$hex);
        }
        $n = $this->seriesColorCount();
        $to = $this->config->seriesPalette($n);

        if ($this->config->animate && $this->boundArea !== null) {
            $fromRows = $this->colorsToRows($this->padColors($from, $n, $to));
            $toRows = $this->colorsToRows($to);
            $this->colorAnimating = true;
            $this->colorAnimator->animate(
                $fromRows,
                $toRows,
                $this->config->animationDuration,
                function (array $rows): void {
                    $this->displayColors = $this->rowsToColors($rows);
                    $this->redraw();
                },
                function () use ($to): void {
                    $this->colorAnimating = false;
                    $this->displayColors = $to;
                    $this->redraw();
                },
            );
        } else {
            $this->displayColors = $to;
            $this->redraw();
        }

        return $this;
    }

    /** The animator driving series-colour tweens (seekable in tests). */
    public function getColorAnimator(): Animator
    {
        return $this->colorAnimator;
    }

    /** Pad a colour list to $n entries, back-filling from $fallback / palette. */
    private function padColors(array $colors, int $n, array $fallback): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = $colors[$i] ?? ($fallback[$i] ?? $this->config->colorAt($i));
        }

        return $out;
    }

    /** Map 0xRRGGBB ints into the Animator's list-of-[r,g,b] rows (floats 0..1). */
    private function colorsToRows(array $colors): array
    {
        $rows = [];
        foreach ($colors as $hex) {
            $c = Color::rgb((int) $hex);
            $rows[] = [$c->r, $c->g, $c->b];
        }

        return $rows;
    }

    /** Inverse of {@see self::colorsToRows()}: [r,g,b] floats back to 0xRRGGBB. */
    private function rowsToColors(array $rows): array
    {
        $out = [];
        foreach ($rows as [$r, $g, $b]) {
            $out[] = Color::rgb255(
                (int) round(max(0.0, min(1.0, $r)) * 255),
                (int) round(max(0.0, min(1.0, $g)) * 255),
                (int) round(max(0.0, min(1.0, $b)) * 255),
            )->toHex();
        }

        return $out;
    }

    /* ====================== AreaDelegate drawing ====================== */

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $W = (float) $p->areaWidth;
        $H = (float) $p->areaHeight;

        $this->drawBackground($ctx, $W, $H);

        $view = new ChartView($this->config);
        $view->plot = $this->computePlot($W, $H);
        $view->seriesColors = $this->currentSeriesColors();

        if ($this->config->showTitle && $this->config->title !== '') {
            $this->drawTitle($ctx);
        }

        RendererFactory::make($this->type)->render($ctx, $this, $view);

        $this->drawLegend($ctx, $view);

        if ($this->dragBox !== null && $this->dragMode === 'box') {
            $this->drawZoomBox($ctx, $view);
        }

        if ($this->hover !== null) {
            $this->drawTooltip($ctx, $view);
        }

        $this->view = $view; // cached for pointer→data mapping
    }

    public function mouse(AreaMouseEvent $e): void
    {
        if ($this->view === null) {
            return;
        }

        if ($e->isDoubleClick()) {
            $this->onDoubleClick($e);

            return;
        }

        if ($e->down === 1) {
            $this->beginDrag($e);

            return;
        }

        if ($e->held !== 0 && $this->dragging) {
            $this->updateDrag($e);

            return;
        }

        if ($e->up !== 0) {
            $this->endDrag($e);

            return;
        }

        // Plain pointer move with no button held → hover (tooltip follows cursor).
        if ($e->down === 0 && $e->up === 0 && $e->held === 0) {
            $this->updateHover($e);
        }
    }

    public function key(AreaKeyEvent $e): bool
    {
        if ($this->view === null) {
            return false;
        }
        if ($e->isKeyDown(43) || $e->isKeyDown(61)) { // '+' or '='
            $this->zoomAtCenter(1.3);

            return true;
        }
        if ($e->isKeyDown(45)) { // '-'
            $this->zoomAtCenter(1.0 / 1.3);

            return true;
        }
        if ($e->isKeyDown(48)) { // '0'
            $this->zoom->reset();
            $this->redraw();

            return true;
        }

        return false;
    }

    /* ============================ Internals ============================ */

    private function syncDisplayImmediate(): void
    {
        $this->displayValues = [];
        foreach ($this->datasets as $d) {
            $row = [];
            foreach ($d->data as $v) {
                $row[] = $v === null ? null : (float) $v;
            }
            $this->displayValues[] = $row;
        }
    }

    private function drawBackground(DrawContext $ctx, float $W, float $H): void
    {
        $ctx->fillRect(0.0, 0.0, $W, $H, Brush::rgb($this->config->background));
    }

    private function drawTitle(DrawContext $ctx): void
    {
        $c = $this->config;
        $f = new FontDescriptor($c->fontFamily, $c->titleSize, TextWeight::Bold);
        $ctx->drawString($c->title, $f, Color::rgb($c->titleColor), $c->padding[3], $c->padding[0], null, DrawTextAlign::Left);
    }

    private function drawLegend(DrawContext $ctx, ChartView $view): void
    {
        $c = $this->config;
        if (! $c->showLegend || $view->legend === []) {
            return;
        }
        $f = $view->fontSmall;
        $items = $view->legend;

        if ($c->legendPosition === 'right') {
            $x = $view->plot[0] + $view->plot[2] + 14.0;
            $y0 = $view->plot[1] + 2.0;
            foreach ($items as [$label, $color]) {
                $ctx->fillRoundedRect($x, $y0, 12.0, 12.0, 3.0, Brush::rgb($color));
                $ctx->drawString((string) $label, $f, Color::rgb($c->legendColor), $x + 18.0, $y0 + 1.0, null, DrawTextAlign::Left);
                $y0 += 20.0;
            }

            return;
        }

        $x = $c->padding[3];
        $y0 = $c->legendPosition === 'top'
            ? $c->padding[0]
            : ($view->plot[1] + $view->plot[3] + 6.0);
        foreach ($items as [$label, $color]) {
            $ctx->fillRoundedRect($x, $y0, 12.0, 12.0, 3.0, Brush::rgb($color));
            $ctx->drawString((string) $label, $f, Color::rgb($c->legendColor), $x + 18.0, $y0 + 1.0, null, DrawTextAlign::Left);
            $y0 += 20.0;
        }
    }

    /** @return array{float,float,float,float} */
    private function computePlot(float $W, float $H): array
    {
        $c = $this->config;
        $pad = $c->padding;
        $left = $pad[3];
        $right = $pad[2];
        $top = $pad[0];
        $bottom = $pad[1];
        $titleH = ($c->showTitle && $c->title !== '') ? $c->titleSize + 14.0 : 0.0;

        if ($c->showLegend) {
            $items = $this->legendItemCount();
            if ($items > 0) {
                if ($c->legendPosition === 'right') {
                    $right += 150.0;
                } elseif ($c->legendPosition === 'top') {
                    $top += 24.0 + $items * 16.0;
                } elseif ($c->legendPosition === 'bottom') {
                    $bottom += 24.0 + $items * 16.0;
                }
            }
        }

        $x = $left;
        $y = $top + $titleH;
        $w = max(10.0, $W - $left - $right);
        $h = max(10.0, $H - $top - $bottom - $titleH);

        return [$x, $y, $w, $h];
    }

    private function legendItemCount(): int
    {
        if ($this->type->isCartesian()) {
            return count($this->datasets);
        }

        return count($this->displayValues[0] ?? []);
    }

    /**
     * Draw the translucent selection rectangle shown while box-zooming.
     * Clamped to the plot area so it never bleeds over axes or legend.
     */
    private function drawZoomBox(DrawContext $ctx, ChartView $view): void
    {
        [$x0, $y0, $x1, $y1] = $this->dragBox;
        [$px, $py, $pw, $ph] = $view->plot;
        $rx = max($px, min($x0, $x1));
        $ry = max($py, min($y0, $y1));
        $rx2 = min($px + $pw, max($x0, $x1));
        $ry2 = min($py + $ph, max($y0, $y1));
        $rw = $rx2 - $rx;
        $rh = $ry2 - $ry;
        if ($rw <= 0.0 || $rh <= 0.0) {
            return;
        }
        $ctx->fillRect($rx, $ry, $rw, $rh, Brush::rgb(0x3b82f6, 0.18));
        $ctx->strokeRect($rx, $ry, $rw, $rh, Brush::rgb(0x3b82f6, 0.9), StrokeParams::solid(1.5));
    }

    private function onDoubleClick(AreaMouseEvent $e): void
    {
        if ($this->type->isCartesian()) {
            $dx = $this->view->pxToX($e->x);
            $dy = $this->view->pxToY($e->y);
            if ($e->isCtrlHeld() || $e->isShiftHeld()) {
                $this->zoom->reset();
            } elseif ($this->zoom->isNearFull()) {
                $this->zoom->zoomAt(2.2, $dx, $dy);
            } else {
                $this->zoom->reset();
            }
            $this->redraw();

            return;
        }

        $this->togglePieExplode();
    }

    private function beginDrag(AreaMouseEvent $e): void
    {
        $this->dragging = true;
        $this->dragBox = null;
        $this->hover = null;

        if (! $this->type->isCartesian()) {
            $this->dragMode = 'none'; // pie/doughnut use double-click to explode
        } elseif ($e->isShiftHeld() && $this->config->zoomEnabled) {
            $this->dragMode = 'pinch';
        } elseif (! $this->zoom->isNearFull() && $this->config->panEnabled) {
            $this->dragMode = 'pan'; // already zoomed → drag pans the viewport
        } elseif ($this->config->zoomEnabled) {
            $this->dragMode = 'box'; // at full domain → drag selects a region to zoom into
        } else {
            $this->dragMode = 'none';
        }

        $this->dragStartPx = [$e->x, $e->y];
        $this->dragStartDomain = [$this->zoom->xMin, $this->zoom->xMax, $this->zoom->yMin, $this->zoom->yMax];
        $this->dragAnchor = [$this->view->pxToX($e->x), $this->view->pxToY($e->y)];
    }

    private function updateDrag(AreaMouseEvent $e): void
    {
        [$sx, $sy] = $this->dragStartPx;
        [$xMin, $xMax, $yMin, $yMax] = $this->dragStartDomain;
        [$ax, $ay] = $this->dragAnchor;
        [$px, $py, $pw, $ph] = $this->view->plot;

        if ($this->dragMode === 'pinch') {
            $dx = $e->x - $sx;
            $factor = exp(-$dx / max(1.0, $pw) * 2.5);
            $this->zoom->setDomain($xMin, $xMax, $yMin, $yMax);
            $this->zoom->zoomAt($factor, $ax, $ay);
        } elseif ($this->dragMode === 'pan') {
            $dxFrac = ($e->x - $sx) / max(1.0, $pw);
            $dyFrac = ($e->y - $sy) / max(1.0, $ph);
            $this->zoom->setDomain($xMin, $xMax, $yMin, $yMax);
            $this->zoom->pan($dxFrac, $dyFrac);
        } elseif ($this->dragMode === 'box') {
            // Track the selection rectangle; the domain is committed on release.
            $this->dragBox = [$sx, $sy, $e->x, $e->y];
        }

        $this->redraw();
    }

    /**
     * Finalize an interaction on mouse-up. For a box-zoom gesture, convert the
     * dragged rectangle to a data domain and jump to it; pan/pinch need nothing
     * extra because the domain was already updated live during the drag.
     */
    private function endDrag(AreaMouseEvent $e): void
    {
        if ($this->dragMode === 'box' && $this->dragBox !== null) {
            [$x0, $y0, $x1, $y1] = $this->dragBox;
            if (abs($x1 - $x0) >= 4.0 && abs($y1 - $y0) >= 4.0) {
                $dx0 = $this->view->pxToX(min($x0, $x1));
                $dx1 = $this->view->pxToX(max($x0, $x1));
                $dy0 = $this->view->pxToY(min($y0, $y1));
                $dy1 = $this->view->pxToY(max($y0, $y1));
                $this->zoom->zoomTo(
                    min($dx0, $dx1),
                    max($dx0, $dx1),
                    min($dy0, $dy1),
                    max($dy0, $dy1),
                );
            }
        }

        $this->dragging = false;
        $this->dragBox = null;
        $this->redraw();
    }

    /**
     * Resolve the hovered element from a plain pointer move. Bars win when the
     * cursor is inside a bar's box; otherwise we snap to the nearest plotted
     * point (line / scatter). Repaint only when the target — or, while hovering,
     * the cursor — changes, so the tooltip tracks the pointer without thrashing.
     */
    private function updateHover(AreaMouseEvent $e): void
    {
        if ($this->view === null) {
            return;
        }
        if (! $this->type->isCartesian()) {
            $this->updateHoverPie($e);

            return;
        }

        $best = null;
        foreach ($this->view->barHitboxes as [$i, $j, $x, $y, $w, $h]) {
            if ($e->x >= $x && $e->x <= $x + $w && $e->y >= $y && $e->y <= $y + $h) {
                $best = ['i' => $i, 'j' => $j];

                break;
            }
        }
        if ($best === null) {
            $bestD = 16.0 * 16.0;
            foreach ($this->view->points as [$i, $j, $px, $py]) {
                $dd = ($e->x - $px) ** 2 + ($e->y - $py) ** 2;
                if ($dd <= $bestD) {
                    $bestD = $dd;
                    $best = ['i' => $i, 'j' => $j];
                }
            }
        }

        $this->setHover($best, $e);
    }

    private function updateHoverPie(AreaMouseEvent $e): void
    {
        $v = $this->view;
        if ($v->pieCenter === null) {
            $this->setHover(null, $e);

            return;
        }
        [$cx, $cy] = $v->pieCenter;
        $best = null;
        foreach ($v->pieSlices as $idx => $s) {
            $dx = $e->x - ($cx + $s['ox']);
            $dy = $e->y - ($cy + $s['oy']);
            $r = sqrt($dx * $dx + $dy * $dy);
            if ($r < $v->pieInner - 2.0 || $r > $v->pieRadius + 2.0) {
                continue;
            }
            $phi = atan2($dy, $dx);
            $rel = $phi - $s['a0'];
            while ($rel < 0.0) {
                $rel += 2.0 * M_PI;
            }
            while ($rel >= 2.0 * M_PI) {
                $rel -= 2.0 * M_PI;
            }
            if ($rel <= $s['sweep'] + 1e-6) {
                $best = ['slice' => $idx];

                break;
            }
        }
        $this->setHover($best, $e);
    }

    private function setHover(?array $h, AreaMouseEvent $e): void
    {
        $this->hoverPx = [$e->x, $e->y];
        $key = $h === null ? null : implode(':', $h);
        $prev = $this->hover === null ? null : implode(':', $this->hover);
        $this->hover = $h;
        if ($key !== $prev || $h !== null) {
            $this->redraw();
        }
    }

    /** Draw the floating tooltip near the cursor (clamped to the plot rect). */
    private function drawTooltip(DrawContext $ctx, ChartView $view): void
    {
        $c = $this->config;
        [$hx, $hy] = $this->hoverPx;

        if ($this->type->isCartesian()) {
            $i = $this->hover['i'] ?? -1;
            $j = $this->hover['j'] ?? -1;
            $d = $this->datasets[$i] ?? null;
            $val = ($this->displayValues[$i] ?? [])[$j] ?? null;
            if ($d === null || $val === null) {
                return;
            }
            $text = $d->label . ': ' . $this->fmtVal($val);
        } else {
            $slice = $this->hover['slice'] ?? -1;
            $s = $view->pieSlices[$slice] ?? null;
            if ($s === null) {
                return;
            }
            $total = 0.0;
            foreach ($view->pieSlices as $p) {
                $total += $p['value'];
            }
            $pct = $total > 0 ? round($s['value'] / $total * 100.0) . '%' : '0%';
            $text = $s['label'] . ': ' . $this->fmtVal($s['value']) . ' (' . $pct . ')';
        }

        $fs = $c->axisFontSize + 1.0;
        $font = new FontDescriptor($c->fontFamily, $fs, TextWeight::Medium);

        // Measure exact text extents so the box hugs the text with even padding.
        $aStr = new AttributedString();
        $aStr->append($text, Attribute::fromColor(Color::rgb($c->tooltipText)));
        $layout = new TextLayout($aStr, $font, 1.0e6, DrawTextAlign::Left);
        [$tw, $th] = $layout->extents();
        $layout->free();

        $padX = 8.0;
        $padY = 4.0;
        $w = max(60.0, $tw + $padX * 2.0);
        $h = $th + $padY * 2.0;

        [$px, $py, $pw, $ph] = $view->plot;
        $tx = $hx + 14.0;
        $ty = $hy + 14.0;
        if ($tx + $w > $px + $pw) {
            $tx = $hx - 14.0 - $w;
        }
        if ($ty + $h > $py + $ph) {
            $ty = $hy - 14.0 - $h;
        }
        $tx = max($tx, $px);
        $ty = max($ty, $py);

        $ctx->fillRoundedRect($tx, $ty, $w, $h, 6.0, Brush::rgb($c->tooltipBackground));
        $ctx->strokeRect($tx, $ty, $w, $h, Brush::rgb($c->tooltipBorder), StrokeParams::solid(1.0));
        $this->drawTooltipArrow($ctx, $view, $tx, $ty, $w, $h);
        // Centered horizontally within the box; top-aligned with even vertical padding.
        $ctx->drawString($text, $font, Color::rgb($c->tooltipText), $tx+$padX, $ty + $padY, $w, DrawTextAlign::Center);
    }

    /**
     * Draw the little triangle that connects the tooltip bubble to the hovered
     * data point, on whichever edge of the box faces that point.
     */
    private function drawTooltipArrow(DrawContext $ctx, ChartView $view, float $tx, float $ty, float $w, float $h): void
    {
        $pt = $this->hoverPointPx($view);
        if ($pt === null) {
            return;
        }
        [$dx, $dy] = $pt;
        $len = 9.0;
        $half = 6.0;
        $bg = Brush::rgb($this->config->tooltipBackground);
        $border = Brush::rgb($this->config->tooltipBorder);

        if ($dx <= $tx) {                                   // left edge
            $yc = max($ty + $half, min($ty + $h - $half, $dy));
            $tri = [[$tx, $yc], [$tx + $len, $yc - $half], [$tx + $len, $yc + $half]];
        } elseif ($dx >= $tx + $w) {                        // right edge
            $yc = max($ty + $half, min($ty + $h - $half, $dy));
            $tri = [[$tx + $w, $yc], [$tx + $w - $len, $yc - $half], [$tx + $w - $len, $yc + $half]];
        } elseif ($dy <= $ty) {                             // top edge
            $xc = max($tx + $half, min($tx + $w - $half, $dx));
            $tri = [[$xc, $ty], [$xc - $half, $ty + $len], [$xc + $half, $ty + $len]];
        } else {                                            // bottom edge
            $xc = max($tx + $half, min($tx + $w - $half, $dx));
            $tri = [[$xc, $ty + $h], [$xc - $half, $ty + $h - $len], [$xc + $half, $ty + $h - $len]];
        }

        $ctx->fillPolygon($tri, $bg);
        $ctx->strokePolygon($tri, $border, StrokeParams::solid(1.0));
    }

    /** Pixel coordinate of the hovered data point (cartesian point/bar-top or pie-slice centroid). */
    private function hoverPointPx(ChartView $view): ?array
    {
        if ($this->type->isCartesian()) {
            $i = $this->hover['i'] ?? -1;
            $j = $this->hover['j'] ?? -1;
            foreach ($view->points as [$pi, $pj, $ptX, $ptY]) {
                if ($pi === $i && $pj === $j) {
                    return [$ptX, $ptY];
                }
            }
            foreach ($view->barHitboxes as [$bi, $bj, $bx, $by, $bw, $bh]) {
                if ($bi === $i && $bj === $j) {
                    return [$bx + $bw / 2.0, $by];
                }
            }

            return null;
        }

        $slice = $this->hover['slice'] ?? -1;
        $s = $view->pieSlices[$slice] ?? null;
        if ($s === null || $view->pieCenter === null) {
            return null;
        }
        [$cx, $cy] = $view->pieCenter;
        $mid = $s['a0'] + $s['sweep'] / 2.0;
        $r = ($view->pieInner + $view->pieRadius) / 2.0;

        return [$cx + $s['ox'] + cos($mid) * $r, $cy + $s['oy'] + sin($mid) * $r];
    }

    private function fmtVal(float $v): string
    {
        if (abs($v - round($v)) < 1e-6) {
            return (string) (int) round($v);
        }

        return number_format($v, 1, '.', '');
    }

    private function zoomAtCenter(float $factor): void
    {
        if (! $this->type->isCartesian() || ! $this->config->zoomEnabled) {
            return;
        }
        $cx = ($this->zoom->xMin + $this->zoom->xMax) / 2.0;
        $cy = ($this->zoom->yMin + $this->zoom->yMax) / 2.0;
        $this->zoom->zoomAt($factor, $cx, $cy);
        $this->redraw();
    }

    public function redraw(): void
    {
        $this->boundArea?->queueRedrawAll();
    }
}
