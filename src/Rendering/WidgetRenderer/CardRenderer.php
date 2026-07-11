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
 * Self-drawn surface / card renderer.
 *
 * Layers (back to front):
 *  - optional fake elevation: an offset, low-alpha rounded rect behind the
 *    surface (libui has no blur, so this stands in for a drop shadow),
 *  - the surface fill (color.surface),
 *  - an optional hairline border (color.track) when $bordered is true,
 *  - an optional token-driven hover wash when $hovered is true.
 *
 * Hover feedback is consumed from the token system (shared {@see TokenWash}
 * trait) so a hovered card matches ButtonRenderer exactly. No text is drawn, so
 * render() is fully headless-safe.
 */
final class CardRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'card';
    }

    /**
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof CardSpec) {
            throw new \InvalidArgumentException('CardRenderer requires a CardSpec');
        }

        $commands = [];

        if ($spec->elevation > 0.0) {
            $offset = 4.0 * $spec->elevation;
            $on = $tokens->color('color.onSurface');
            $commands[] = new FillRoundedRect(
                $offset,
                $offset,
                $width,
                $height,
                $spec->radius,
                Color::rgba($on->r, $on->g, $on->b, 0.12 * $spec->elevation),
            );
        }

        $commands[] = new FillRoundedRect(
            0.0,
            0.0,
            $width,
            $height,
            $spec->radius,
            $tokens->color('color.surface'),
        );

        if ($spec->bordered) {
            $inset = 0.75;
            $commands[] = new StrokeRoundedRect(
                $inset,
                $inset,
                $width - 2 * $inset,
                $height - 2 * $inset,
                $spec->radius,
                $tokens->color('color.track'),
                StrokeParams::solid(1.0),
            );
        }

        // Token-driven hover wash over the whole surface (cards are always enabled).
        foreach ($this->washCommands(true, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
