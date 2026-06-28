<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Control;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Draw\StrokeParams;
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Composite;

/**
 * A custom-drawn circular/ring progress bar, rendered via an Area.
 *
 * Displays a ring with the progress arc and the percentage text centered
 * inside the ring.
 *
 * ```php
 * $progress = new CircleProgressBar(65);
 * $progress->setColor(Color::rgba(0.0, 0.8, 0.0, 1.0)); // green
 * echo $progress->getProgress(); // 65
 * ```
 */
class CircleProgressBar extends Composite
{
    private readonly Area $area;
    private readonly CircleProgressDelegate $delegate;

    public function __construct(int $initialProgress = 0, int $size = 200)
    {
        $this->delegate = new CircleProgressDelegate($initialProgress, $size);
        $this->area = Area::scrolling($this->delegate, $size, $size);
    }

    public function root(): Control
    {
        return $this->area;
    }

    /**
     * Get current progress (0-100).
     */
    public function getProgress(): int
    {
        return $this->delegate->progress;
    }

    /**
     * Set progress value (0-100).
     *
     * Values below 0 are clamped to 0, above 100 to 100.
     *
     * @return $this
     */
    public function setProgress(int $percent): static
    {
        $this->delegate->progress = max(0, min(100, $percent));
        $this->delegate->redraw();
        return $this;
    }

    /**
     * Get the current progress arc color.
     */
    public function getColor(): Color
    {
        return $this->delegate->color;
    }

    /**
     * Set the progress arc color.
     *
     * @return $this
     */
    public function setColor(Color $color): static
    {
        $this->delegate->color = $color;
        $this->delegate->redraw();
        return $this;
    }

    /**
     * Get the ring thickness in pixels.
     */
    public function getThickness(): float
    {
        return $this->delegate->thickness;
    }

    /**
     * Set the ring thickness in pixels.
     *
     * @return $this
     */
    public function setThickness(float $thickness): static
    {
        $this->delegate->thickness = max(1.0, $thickness);
        $this->delegate->redraw();
        return $this;
    }
}

/**
 * @internal Area delegate driving the circular progress bar's drawing.
 */
final class CircleProgressDelegate extends AreaDelegate
{
    private const DEFAULT_COLOR = [0.04, 0.52, 1.0, 1.0];
    private const TRACK_COLOR = [0.88, 0.88, 0.88, 1.0];
    private const TEXT_COLOR = [0.2, 0.2, 0.2, 1.0];

    public int $progress;
    public Color $color;
    public float $thickness = 12.0;
    private int $ringSize;

    public function __construct(int $initialProgress, int $ringSize = 200)
    {
        $this->progress = max(0, min(100, $initialProgress));
        $this->color = Color::rgba(...self::DEFAULT_COLOR);
        $this->ringSize = $ringSize;
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $w = $params->areaWidth;
        $h = $params->areaHeight;

        // When viewport is 0×0 (after tab switch), use content size as fallback.
        // When viewport is correct, center the ring in it.
        if ($w < $this->ringSize || $h < $this->ringSize) {
            $w = $this->ringSize;
            $h = $this->ringSize;
        }

        $cx = $w / 2;
        $cy = $h / 2;

        $minDiameter = $this->thickness * 2 + 8;
        $diameter = max($minDiameter, $this->ringSize - 8);
        $radius = $diameter / 2 - $this->thickness / 2;

        if ($radius <= 0) {
            return;
        }

        $startAngle = -M_PI / 2;

        $trackStroke = new StrokeParams(
            thickness: $this->thickness,
            cap: \Libui\Generated\Enum\DrawLineCap::Round,
            join: \Libui\Generated\Enum\DrawLineJoin::Round,
        );
        $ctx->strokePath(
            Brush::color(Color::rgba(...self::TRACK_COLOR)),
            $trackStroke,
            static fn ($p) => $p->arc($cx, $cy, $radius, 0.0, 2 * M_PI),
        );

        $sweep = ($this->progress / 100.0) * 2 * M_PI;
        if ($sweep > 0) {
            $progressStroke = new StrokeParams(
                thickness: $this->thickness,
                cap: \Libui\Generated\Enum\DrawLineCap::Round,
                join: \Libui\Generated\Enum\DrawLineJoin::Round,
            );
            $ctx->strokePath(
                Brush::color($this->color),
                $progressStroke,
                static fn ($p) => $p->arc($cx, $cy, $radius, $startAngle, $sweep),
            );
        }

        $text = $this->progress . '%';
        $innerDiameter = $diameter - $this->thickness;
        $fontSize = max(14.0, $innerDiameter * 0.10);

        $font = new FontDescriptor('Arial', $fontSize);
        $str = new AttributedString();
        $str->append($text, Attribute::fromColor(Color::rgba(...self::TEXT_COLOR)), Attribute::size($fontSize));

        $layout = new TextLayout($str, $font, $innerDiameter * 2, DrawTextAlign::Left);
        [$textW, $textH] = $layout->extents();
        $ctx->text($layout, $cx - $textW / 2, $cy - $textH / 2);
        $layout->free();
    }
}