<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class ButtonGroupRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'button_group';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof ButtonGroupSpec);
        return [];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
