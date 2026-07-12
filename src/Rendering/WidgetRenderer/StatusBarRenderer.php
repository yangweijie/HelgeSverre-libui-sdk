<?php
declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommandList;

final class StatusBarRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'status_bar';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        assert($spec instanceof StatusBarSpec);

        $bgColor = match ($spec->variant) {
            'success' => new Color(0.2, 0.8, 0.4, 0.1),
            'warning' => new Color(1.0, 0.8, 0.2, 0.1),
            'error' => new Color(1.0, 0.3, 0.3, 0.1),
            default => new Color(0.0, 0.0, 0.0, 0.0),
        };

        if ($bgColor->a <= 0.0) {
            return [];
        }

        return [
            new FillRoundedRect(0, 0, $width, $height, 0.0, $bgColor),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
