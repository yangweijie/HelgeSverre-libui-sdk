<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawCallback;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Renderer for {@see CanvasSpec}: wraps a user-supplied draw callback
 * into a DrawCallback render command.
 */
final class CanvasRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'canvas';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        return $this->buildCommands($spec, $width, $height);
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->buildCommands($spec, $width, $height));
    }

    /**
     * @return list<RenderCommand>
     */
    private function buildCommands(WidgetSpec $spec, float $width, float $height): array
    {
        if (! $spec instanceof CanvasSpec) {
            return [];
        }

        $commands = [];

        // Optional background fill
        if ($spec->background !== null) {
            $hex = (int) $spec->background;
            $commands[] = new FillRoundedRect(0.0, 0.0, $width, $height, 0.0, Color::rgb($hex));
        }

        // The callback itself — executed by CommandExecutor
        $commands[] = new DrawCallback(0.0, 0.0, $width, $height, $spec->callback);

        return $commands;
    }
}
