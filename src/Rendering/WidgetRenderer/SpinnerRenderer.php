<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeArc;
use Yangweijie\Ui2\Rendering\StrokeParams;

final class SpinnerRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'spinner';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof SpinnerSpec);

        $cx = $width / 2;
        $cy = $height / 2;
        $radius = ($spec->size / 2) - $spec->thickness;

        return [
            new StrokeArc($cx, $cy, $radius, 0.0, 270.0, $tokens->color("color.{$spec->color}"), new StrokeParams(thickness: $spec->thickness)),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
