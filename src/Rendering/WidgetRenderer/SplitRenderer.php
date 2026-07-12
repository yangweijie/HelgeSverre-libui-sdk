<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeParams;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

final class SplitRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'split';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof SplitSpec);

        $borderColor = new Color(0.85, 0.85, 0.87, 1.0);
        $commands = [];

        if ($spec->bordered) {
            $commands[] = new StrokeRoundedRect(0, 0, $width, $height, 8.0, $borderColor, new StrokeParams(thickness: 1.0));
        }

        // Divider line between the two panes
        if ($spec->direction === 'horizontal') {
            $divX = $width * $spec->ratio;
            $commands[] = new StrokeLine($divX, 0, $divX, $height, $borderColor, 1.0);
        } else {
            $divY = $height * $spec->ratio;
            $commands[] = new StrokeLine(0, $divY, $width, $divY, $borderColor, 1.0);
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
