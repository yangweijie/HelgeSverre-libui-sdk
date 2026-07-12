<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Self-drawn text label renderer.
 *
 * Renders plain text without background, border, or interaction states. The
 * label is vertically centred in its allocated rectangle and aligned according
 * to the spec (left, center, right). Colour is resolved from the active design
 * tokens so labels automatically adapt to light/dark themes.
 */
final class LabelRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'label';
    }

    /**
     * @return list<\Yangweijie\Ui2\Rendering\RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        return [];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof LabelSpec) {
            throw new \InvalidArgumentException('LabelRenderer requires a LabelSpec');
        }

        $commands = [];

        $text = $spec->text;
        if ($text !== '') {
            $fontSize = $spec->size;
            $font = $tokens->font($fontSize);
            $baseColor = $tokens->color($spec->color) ?? $tokens->color('color.onSurface');
            $color = Color::rgba($baseColor->r, $baseColor->g, $baseColor->b, $baseColor->a * $spec->opacity);

            $str = new AttributedString();
            $str->append($text, Attribute::fromColor($color), Attribute::size($fontSize));

            $align = match ($spec->align) {
                'center' => DrawTextAlign::Center,
                'right' => DrawTextAlign::Right,
                default => DrawTextAlign::Left,
            };

            $layout = new TextLayout($str, $font, $width, $align);
            [$tw, $th] = $layout->extents();

            $x = match ($spec->align) {
                'center' => ($width - $tw) / 2.0,
                'right' => max(0.0, $width - $tw),
                default => 0.0,
            };
            $y = max(0.0, ($height - $th) / 2.0);

            $commands[] = new DrawText($layout, $x, $y);
        }

        return new RenderCommandList($commands);
    }
}
