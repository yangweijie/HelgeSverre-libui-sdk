<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn dialog card surface: a surface fill + hairline border at the given
 * radius. It is the backdrop the {@see DialogBodySpec} title/message and the
 * action {@see ButtonSpec} leaves are painted on top of.
 *
 * No token wash here — the dialog as a whole is a modal focus surface, not a
 * hoverable control.
 */
final class DialogCardRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'dialog_card';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof DialogCardSpec) {
            throw new \InvalidArgumentException('DialogCardRenderer requires a DialogCardSpec');
        }

        $inset = 0.75;

        return [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $tokens->color('color.surface')),
            new StrokeRoundedRect(
                $inset, $inset, $width - 2 * $inset, $height - 2 * $inset,
                $spec->radius, $tokens->color('color.track'), StrokeParams::solid(1.0),
            ),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
