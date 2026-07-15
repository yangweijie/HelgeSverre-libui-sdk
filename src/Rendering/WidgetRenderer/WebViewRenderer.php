<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Renderer for {@see WebViewSpec}.
 *
 * The real browser lives in a native child window managed by
 * {@see \Yangweijie\Ui2\Widgets\Surface}; this renderer only paints the
 * placeholder rectangle that shows for the brief moment before that window
 * is created and glued to the node's rect.
 */
final class WebViewRenderer implements WidgetRenderer
{
    /** Default placeholder fill (Apple system background). */
    private const DEFAULT_BG = 0xF2F2F7;

    public static function type(): string
    {
        return 'webview';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        return $this->buildCommands($spec, $width, $height);
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->buildCommands($spec, $width, $height));
    }

    /**
     * @return list<RenderCommand>
     */
    private function buildCommands(WidgetSpec $spec, float $width, float $height): array
    {
        if (! $spec instanceof WebViewSpec) {
            return [];
        }

        $bg = $spec->background !== null ? (int) $spec->background : self::DEFAULT_BG;

        return [
            new FillRoundedRect(0.0, 0.0, $width, $height, 0.0, Color::rgb($bg)),
        ];
    }
}
