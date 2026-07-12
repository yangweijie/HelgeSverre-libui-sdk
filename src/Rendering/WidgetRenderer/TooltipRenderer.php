<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class TooltipRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'tooltip';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof TooltipSpec);

        return [
            new FillRoundedRect(0, 0, $width, $height, 6.0, new Color(0.15, 0.15, 0.15, 0.9)),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
