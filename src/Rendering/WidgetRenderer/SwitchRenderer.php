<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class SwitchRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'switch';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof SwitchSpec);

        // Default to ON state for static rendering
        $trackColor = $tokens->color('color.accent');
        $thumbX = $width * 0.75;
        $thumbAlpha = 1.0;

        $trackHeight = $height * 0.6;
        $trackY = ($height - $trackHeight) / 2;
        $trackRadius = $trackHeight / 2;

        return [
            new FillRoundedRect(0, $trackY, $width, $trackHeight, $trackRadius, $trackColor),
            new FillCircle($thumbX, $height / 2, $height * 0.22, new Color(1.0, 1.0, 1.0, $thumbAlpha)),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
