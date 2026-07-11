<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;

/**
 * Shared token-driven interaction-state wash.
 *
 * Every interactive self-drawn widget ends its geometry with the same two
 * overlays, decided purely by tokens (Phase 10):
 *  - disabled  -> a mute wash (color.washDisabled)
 *  - hovered   -> a hover wash (color.washHover, which flips to lighten on dark
 *                 themes because the token itself differs per theme)
 *
 * Drawing the wash *inside* the renderer (rather than as a generic Surface
 * overlay) keeps each widget the single owner of its hover/disabled feedback and
 * lets it pick the right corner radius and rect — exactly how ButtonRenderer
 * already worked. ButtonRenderer itself now delegates to this trait so the logic
 * lives in one place.
 */
trait TokenWash
{
    /**
     * Build the wash overlay command for a widget, or null when nothing to draw.
     *
     * @return list<FillRoundedRect>
     */
    protected function washCommands(bool $enabled, bool $hovered, DesignTokens $tokens, float $width, float $height, float $radius): array
    {
        $color = $this->washColor($enabled, $hovered, $tokens);
        if ($color === null) {
            return [];
        }

        $inset = 0.5;

        return [
            new FillRoundedRect(
                $inset,
                $inset,
                $width - 2 * $inset,
                $height - 2 * $inset,
                $radius,
                $color,
            ),
        ];
    }

    /** Resolve the wash colour for the current state, or null. */
    protected function washColor(bool $enabled, bool $hovered, DesignTokens $tokens): ?Color
    {
        if (! $enabled) {
            return $tokens->disabledWash();
        }

        if ($hovered) {
            return $tokens->hoverWash();
        }

        return null;
    }
}
