<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class SkeletonRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'skeleton';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof SkeletonSpec);

        $gray = new Color(0.90, 0.90, 0.92, 1.0);

        return match ($spec->variant) {
            'circle' => [
                new FillCircle($width / 2, $height / 2, min($width, $height) / 2, $gray),
            ],
            default => [
                new FillRoundedRect(0, 0, $width, $height, 4.0, $gray),
            ],
        };
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
