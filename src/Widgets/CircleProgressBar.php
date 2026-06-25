<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Control;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Composite;

/**
 * A custom-drawn circular/ring progress bar, rendered via an Area.
 *
 * ```php
 * $progress = new CircleProgressBar();
 * $progress->setProgress(65);
 * echo $progress->getProgress(); // 65
 * ```
 */
class CircleProgressBar extends Composite
{
    public const SIZE = 120;

    private readonly Area $area;
    private readonly CircleProgressDelegate $delegate;

    public function __construct(int $initialProgress = 0)
    {
        $this->delegate = new CircleProgressDelegate($initialProgress);
        $this->area = new Area($this->delegate, self::SIZE, self::SIZE);
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
        $cx = CircleProgressBar::SIZE / 2;
        $cy = CircleProgressBar::SIZE / 2;
        $radius = $cx - $this->thickness / 2 - 4; // inset by half thickness + padding
        $startAngle = -M_PI / 2; // 12 o'clock

        // --- Background track (full ring) ---
        $trackStroke = new StrokeParams(
            thickness: $this->thickness,
            cap: \Libui\Generated\Enum\DrawLineCap::Round,
            join: \Libui\Generated\Enum\DrawLineJoin::Round,
        );
        $ctx->strokePath(
            Color::rgba(...self::TRACK_COLOR),
            $trackStroke,
            static fn ($p) => $p->arc($cx, $cy, $radius, 0.0, 2 * M_PI),
        );

        // --- Progress arc ---
        $sweep = ($this->progress / 100.0) * 2 * M_PI;
        if ($sweep <= 0) {
            return;
        }

        $progressStroke = new StrokeParams(
            thickness: $this->thickness,
            cap: \Libui\Generated\Enum\DrawLineCap::Round,
            join: \Libui\Generated\Enum\DrawLineJoin::Round,
        );
        $ctx->strokePath(
            $this->color,
            $progressStroke,
            static fn ($p) => $p->arc($cx, $cy, $radius, $startAngle, $sweep),
        );
    }
}