<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;

final class ResizableRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'resizable';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof ResizableSpec);

        $gripColor = new Color(0.7, 0.7, 0.72, 0.5);

        return [
            new StrokeLine($width - 12, $height - 2, $width - 2, $height - 12, $gripColor, 1.0),
            new StrokeLine($width - 8, $height - 2, $width - 2, $height - 8, $gripColor, 1.0),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
