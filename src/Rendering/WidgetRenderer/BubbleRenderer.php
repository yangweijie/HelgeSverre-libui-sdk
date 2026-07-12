<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeParams;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

final class BubbleRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'bubble';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof BubbleSpec);

        $borderColor = new Color(0.85, 0.85, 0.87, 1.0);

        return [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, new Color(1.0, 1.0, 1.0, 0.97)),
            new StrokeRoundedRect(0, 0, $width, $height, $spec->radius, $borderColor, new StrokeParams(thickness: 1.0)),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
