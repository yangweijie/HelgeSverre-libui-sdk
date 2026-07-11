<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Paints a {@see ScrimSpec} as a single flat fill covering the whole node rect.
 * With alpha 0 it is fully transparent (click-catcher only); with a small alpha
 * it adds a subtle dim so the overlay reads as floating above the page.
 */
final class ScrimRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'scrim';
    }

    /** Headless-safe: only draws a fill, no text. */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ScrimSpec) {
            throw new \InvalidArgumentException('ScrimRenderer requires a ScrimSpec');
        }

        return [
            new FillRoundedRect(0, 0, $width, $height, 0, Color::rgba(0.0, 0.0, 0.0, $spec->alpha)),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
