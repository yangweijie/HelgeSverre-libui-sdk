<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Brush;
use Libui\Color;
use Libui\Control;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Yangweijie\Ui2\Composite;

/**
 * A colored dot indicator, rendered via an Area.
 *
 * Useful for showing connection status, online/offline state, etc.
 *
 * ```php
 * $status = new StatusIndicator(Color::rgb(0x22C55E)); // green dot
 * $status->setColor(Color::rgb(0xEF4444));              // red dot
 * ```
 */
class StatusIndicator extends Composite
{
    public const RADIUS = 7;

    private readonly Area $area;
    private readonly StatusDelegate $delegate;

    public function __construct(Color $color)
    {
        $this->delegate = new StatusDelegate($color);
        $this->area = new Area($this->delegate);
        // No timer/setSize needed — Area constructor already handles initial
        // redraw on Windows, and stretchy containers handle sizing.
    }

    public function root(): Control
    {
        return $this->area;
    }

    /**
     * Change the dot color.
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
     * Convenience: set color from a hex integer (e.g. 0x22C55E).
     *
     * @return $this
     */
    public function setColorHex(int $hex): static
    {
        return $this->setColor(Color::rgb($hex));
    }
}

/**
 * @internal Area delegate driving the indicator's custom drawing.
 */
final class StatusDelegate extends AreaDelegate
{
    public Color $color;

    public function __construct(Color $color)
    {
        $this->color = $color;
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $cx = $params->areaWidth / 2;
        $cy = $params->areaHeight / 2;
        $r = StatusIndicator::RADIUS;

        // Outer glow
        $glow = Color::rgba($this->color->r, $this->color->g, $this->color->b, 0.25);
        $ctx->fillCircle($cx, $cy, $r + 1, $glow);

        // Filled dot
        $ctx->fillCircle($cx, $cy, $r - 1, $this->color);
    }
}
