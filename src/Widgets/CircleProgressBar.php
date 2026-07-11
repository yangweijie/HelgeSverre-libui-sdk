<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\Color;
use Libui\Control;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\Rendering\DesignTokens;

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
    public DesignTokens $tokens;

    public function __construct(int $initialProgress = 0, int $size = 200, ?DesignTokens $tokens = null)
    {
        $this->tokens = $tokens ?? new DesignTokens();
        $this->delegate = new CircleProgressDelegate($initialProgress, $size, $this->tokens);
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
     * Get the current progress arc color (explicit override or theme token).
     */
    public function getColor(): Color
    {
        return $this->delegate->progressColor();
    }

    /**
     * Set the progress arc color explicitly (overrides the theme token).
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
     * Return the active design tokens.
     */
    public function getTokens(): DesignTokens
    {
        return $this->tokens;
    }

    /**
     * Apply a theme override (deep-merged on top of the current tokens) and
     * repaint. The previous token set is never mutated.
     *
     * @param array<string, mixed> $overrides
     *
     * @return $this
     */
    public function setTheme(array $overrides): static
    {
        $this->tokens = $this->tokens->applyTheme($overrides);
        $this->delegate->tokens = $this->tokens;
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
