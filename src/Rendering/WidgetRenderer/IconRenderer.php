<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Renders an icon placeholder as a filled circle.
 * Replace with real vector path rendering when the icon pipeline is ready.
 */
final class IconRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'icon';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof IconSpec);

        $color = $tokens->color("color.{$spec->color}");
        $r = min($width, $height) / 2.0;

        return [
            new FillCircle(
                cx: $width / 2.0,
                cy: $height / 2.0,
                radius: $r,
                color: $color,
            ),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }


}
