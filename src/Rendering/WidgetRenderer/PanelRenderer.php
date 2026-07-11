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
 * Renderer for {@see PanelSpec}: a solid surface-colour rounded rectangle with
 * an optional hairline border and a low-alpha fake elevation shadow. No text
 * and no hover state, so it is fully headless-safe.
 */
final class PanelRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'panel';
    }

    /**
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof PanelSpec) {
            throw new \InvalidArgumentException('PanelRenderer requires a PanelSpec');
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

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
