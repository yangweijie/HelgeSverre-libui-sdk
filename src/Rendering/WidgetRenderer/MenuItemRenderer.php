<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class MenuItemRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'menu_item';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof MenuItemSpec);

        if ($spec->danger) {
            return [
                new FillRoundedRect(0, 0, $width, $height, 4.0, new Color(1.0, 0.3, 0.3, 0.08)),
            ];
        }

        return [];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
