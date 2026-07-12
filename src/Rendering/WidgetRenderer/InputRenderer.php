<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeParams;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

final class InputRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'input';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof InputSpec);

        $commands = [];

        // Light surface background
        $commands[] = new FillRoundedRect(0, 0, $width, $height, $spec->radius, new Color(0.95, 0.95, 0.97, 1.0));

        // Focus border
        if ($spec->focused) {
            $commands[] = new StrokeRoundedRect(0, 0, $width, $height, $spec->radius, $tokens->color('color.accent'), new StrokeParams(thickness: 1.5));
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
