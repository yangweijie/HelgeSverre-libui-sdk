<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;

/**
 * Self-drawn tab: a caption that shows an active underline (primary) when
 * selected, a primary caption, and a token-driven hover wash when hovered.
 *
 * The tab strip itself is just a row container (role TabList) of these leaves;
 * the active tab's panel is a separate subtree owned by the
 * {@see \Yangweijie\Ui2\Widgets\TabControl}.
 */
final class TabRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'tab';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof TabSpec) {
            throw new \InvalidArgumentException('TabRenderer requires a TabSpec');
        }

        $commands = [];

        // Hover/disabled wash behind the label.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        // Active underline indicator at the bottom edge.
        if ($spec->active) {
            $primary = $tokens->color('color.primary');
            $commands[] = new StrokeLine(8, $height - 2, $width - 8, $height - 2, $primary, 2.5);
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $primary = $tokens->color('color.primary');
        $onSurface = $tokens->color('color.onSurface');
        if (! $spec->enabled) {
            $onSurface = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4);
        }
        $color = $spec->active ? $primary : $onSurface;

        $fontSize = min($height * 0.42, 14.0);
        $font = $tokens->font($fontSize);
        $str = new AttributedString();
        $str->append($spec->label, Attribute::fromColor($color), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, $width, DrawTextAlign::Center);
        [$tw, $th] = $layout->extents();
        $commands[] = new DrawText($layout, ($width - $tw) / 2, ($height - $th) / 2);

        return new RenderCommandList($commands);
    }
}
