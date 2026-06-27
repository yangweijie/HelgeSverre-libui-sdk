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

    public function __construct(int $initialProgress = 0)
    {
        $this->delegate = new CircleProgressDelegate($initialProgress);
        $this->area = new Area($this->delegate);
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
    /** Default progress bar colour (macOS accent blue). */
    private const DEFAULT_COLOR = [0.04, 0.52, 1.0, 1.0]; // #0A84FF

    /** Background track colour (light gray). */
    private const TRACK_COLOR = [0.88, 0.88, 0.88, 1.0]; // #E0E0E0

    /** Text colour (dark gray). */
    private const TEXT_COLOR = [0.2, 0.2, 0.2, 1.0];

    public int $progress;
    public Color $color;
    public float $thickness = 12.0;

    public function __construct(int $initialProgress)
    {
        $this->progress = max(0, min(100, $initialProgress));
        $this->color = Color::rgba(...self::DEFAULT_COLOR);
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $w = $params->areaWidth;
        $h = $params->areaHeight;
        $cx = $w / 2;
        $cy = $h / 2;

        // Minimum ring envelope: the ring needs at least (thickness + 8) diameter
        // to be visible. When the area is larger, the ring scales up to fill it.
        $minDiameter = $this->thickness * 2 + 8;
        $diameter = max($minDiameter, min($w, $h) - 8);
        $radius = $diameter / 2 - $this->thickness / 2;

        if ($radius <= 0) {
            return;
        }

        $startAngle = -M_PI / 2; // 12 o'clock

        // --- Background track (full ring) ---
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

        // --- Progress arc ---
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

        // --- Center text (percentage) ---
        $text = $this->progress . '%';
        $innerDiameter = $diameter - $this->thickness;

        // Font size scales with the ring: min 14pt for small rings, ~10% of inner diameter for large ones.
        $fontSize = max(14.0, $innerDiameter * 0.10);

        // Build attributed string with explicit size (same pattern as monitor.php label())
        $font = new FontDescriptor('Arial', $fontSize);
        $str = new AttributedString();
        $str->append(
            $text,
            Attribute::fromColor(Color::rgba(...self::TEXT_COLOR)),
            Attribute::size($fontSize),
        );

        // Center-align within innerDiameter box, positioned so the box is centered in the ring
        $layout = new TextLayout($str, $font, $innerDiameter, DrawTextAlign::Center);
        $ctx->text($layout, $cx - $innerDiameter / 2, $cy - $fontSize / 2);
        $layout->free();
    }
}