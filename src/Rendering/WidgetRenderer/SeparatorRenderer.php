<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;

final class SeparatorRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'separator';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof SeparatorSpec);

        $color = $tokens->color("color.{$spec->color}");

        if ($spec->orientation === 'vertical') {
            $cx = $width / 2.0;
            return [
                new StrokeLine(
                    x0: $cx,
                    y0: 0.0,
                    x1: $cx,
                    y1: $height,
                    color: $color,
                    thickness: $spec->thickness,
                ),
            ];
        }

        // Horizontal
        $cy = $height / 2.0;
        return [
            new StrokeLine(
                x0: 0.0,
                y0: $cy,
                x1: $width,
                y1: $cy,
                color: $color,
                thickness: $spec->thickness,
            ),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }


}
