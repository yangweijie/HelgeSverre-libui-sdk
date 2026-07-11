<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn scroll viewport chrome (.scroll_view).
 *
 * Because the scrolled content is a sub-tree painted by the Surface's own
 * layout recursion (clipped + translated by the node's scrollX/scrollY), this
 * renderer only draws the *chrome*: a surface-filled rounded box, a hairline
 * border, and — when the content overflows — a scrollbar track plus a thumb
 * whose position reflects the current scroll offset.
 *
 * Geometry-only (no TextLayout), so it is safe to exercise headlessly.
 */
final class ScrollViewRenderer implements WidgetRenderer
{
    /** Width/height of the scrollbar gutter reserved on the right / bottom edge. */
    public const GUTTER = 12.0;

    /** Padding between the viewport edge and the scrollbar track. */
    public const TRACK_INSET = 4.0;

    /** Minimum thumb length so very long content stays grabbable. */
    public const MIN_THUMB = 28.0;

    public static function type(): string
    {
        return 'scroll_view';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ScrollViewSpec) {
            throw new \InvalidArgumentException('ScrollViewRenderer requires a ScrollViewSpec');
        }

        $bg = $tokens->color('color.surface');
        $border = $spec->enabled
            ? $tokens->color('color.track')
            : Color::rgba(...$this->dim($tokens->color('color.track')));

        $commands = [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $bg),
            new StrokeRoundedRect(0.75, 0.75, $width - 1.5, $height - 1.5, $spec->radius, $border, StrokeParams::solid(1.5)),
        ];

        if ($spec->vertical) {
            $thumb = $this->verticalThumb($spec, $width, $height);
            if ($thumb !== null) {
                $commands[] = $this->track($tokens, $thumb[0] - 2, self::TRACK_INSET, self::GUTTER - 4, $height - 2 * self::TRACK_INSET, $spec->radius);
                $commands[] = $this->thumb($tokens, $thumb[0], $thumb[1], $thumb[2], $thumb[3]);
            }
        }

        if ($spec->horizontal) {
            $thumb = $this->horizontalThumb($spec, $width, $height);
            if ($thumb !== null) {
                $commands[] = $this->track($tokens, self::TRACK_INSET, $thumb[1] - 2, $width - 2 * self::TRACK_INSET, self::GUTTER - 4, $spec->radius);
                $commands[] = $this->thumb($tokens, $thumb[0], $thumb[1], $thumb[2], $thumb[3]);
            }
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }

    /**
     * Vertical scrollbar thumb rect [x, y, w, h] in local coords, or null when
     * the content does not overflow vertically.
     *
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public function verticalThumb(ScrollViewSpec $spec, float $width, float $height): ?array
    {
        if (! $spec->vertical || $spec->viewportHeight <= 0 || $spec->contentHeight <= $spec->viewportHeight) {
            return null;
        }

        $trackX = $width - self::GUTTER + 2;
        $trackY = self::TRACK_INSET;
        $trackW = self::GUTTER - 4;
        $trackH = $height - 2 * self::TRACK_INSET;

        $maxScroll = $spec->contentHeight - $spec->viewportHeight;
        $thumbH = (float) max(self::MIN_THUMB, min($trackH, $trackH * ($spec->viewportHeight / $spec->contentHeight)));
        $travel = $trackH - $thumbH;
        $thumbY = $trackY + $travel * ($spec->scrollY / $maxScroll);

        return [$trackX, $thumbY, $trackW, $thumbH];
    }

    /**
     * Horizontal scrollbar thumb rect [x, y, w, h] in local coords, or null.
     *
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public function horizontalThumb(ScrollViewSpec $spec, float $width, float $height): ?array
    {
        if (! $spec->horizontal || $spec->viewportWidth <= 0 || $spec->contentWidth <= $spec->viewportWidth) {
            return null;
        }

        $trackX = self::TRACK_INSET;
        $trackY = $height - self::GUTTER + 2;
        $trackW = $width - 2 * self::TRACK_INSET;
        $trackH = self::GUTTER - 4;

        $maxScroll = $spec->contentWidth - $spec->viewportWidth;
        $thumbW = (float) max(self::MIN_THUMB, min($trackW, $trackW * ($spec->viewportWidth / $spec->contentWidth)));
        $travel = $trackW - $thumbW;
        $thumbX = $trackX + $travel * ($spec->scrollX / $maxScroll);

        return [$thumbX, $trackY, $thumbW, $trackH];
    }

    /** Map a vertical thumb-centre Y (local coords) back to a scrollY value. */
    public function scrollYForThumbCenter(ScrollViewSpec $spec, float $thumbCenterY, float $height): float
    {
        if (! $spec->vertical || $spec->viewportHeight <= 0 || $spec->contentHeight <= $spec->viewportHeight) {
            return 0.0;
        }

        $trackY = self::TRACK_INSET;
        $trackH = $height - 2 * self::TRACK_INSET;
        $thumbH = (float) max(self::MIN_THUMB, min($trackH, $trackH * ($spec->viewportHeight / $spec->contentHeight)));
        $travel = $trackH - $thumbH;
        $ratio = ($thumbCenterY - $trackY - $thumbH / 2) / $travel;

        return $this->clampScroll($ratio * ($spec->contentHeight - $spec->viewportHeight), $spec);
    }

    private function clampScroll(float $value, ScrollViewSpec $spec): float
    {
        $max = max(0.0, $spec->vertical
            ? $spec->contentHeight - $spec->viewportHeight
            : $spec->contentWidth - $spec->viewportWidth);

        return (float) max(0.0, min($max, $value));
    }

    private function track(DesignTokens $tokens, float $x, float $y, float $w, float $h, float $radius): FillRoundedRect
    {
        $tint = $tokens->color('color.onSurface');

        return new FillRoundedRect($x, $y, $w, $h, $radius, Color::rgba($tint->r, $tint->g, $tint->b, 0.08));
    }

    private function thumb(DesignTokens $tokens, float $x, float $y, float $w, float $h): FillRoundedRect
    {
        $tint = $tokens->color('color.onSurface');

        return new FillRoundedRect($x, $y, $w, $h, min($w, $h) / 2, Color::rgba($tint->r, $tint->g, $tint->b, 0.32));
    }

    /** Dim a colour to 40% alpha (used for the disabled border). */
    private function dim(Color $c): array
    {
        return [$c->r, $c->g, $c->b, 0.4];
    }
}
