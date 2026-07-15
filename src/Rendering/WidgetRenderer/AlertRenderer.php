<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class AlertRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'alert';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof AlertSpec);

        [$accentColor, $bgColor] = match ($spec->variant) {
            'success' => [Color::rgba(0.2, 0.8, 0.4), Color::rgba(0.2, 0.8, 0.4, 0.08)],
            'warning' => [Color::rgba(1.0, 0.8, 0.2), Color::rgba(1.0, 0.8, 0.2, 0.08)],
            'error' => [Color::rgba(1.0, 0.3, 0.3), Color::rgba(1.0, 0.3, 0.3, 0.08)],
            default => [Color::rgba(0.2, 0.5, 1.0), Color::rgba(0.2, 0.5, 1.0, 0.08)],
        };

        return [
            // Tinted background
            new FillRoundedRect(0, 0, $width, $height, 8.0, $bgColor),
            // Accent strip at left edge
            new FillRoundedRect(0, 0, 4.0, $height, 2.0, $accentColor),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
