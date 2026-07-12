<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Grid is a pure layout container — it produces no visual commands.
 * The layout engine divides width into `columns` equal slots with `gap`.
 */
final class GridRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'grid';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        return [];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList([]);
    }
}
