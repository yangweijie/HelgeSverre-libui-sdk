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
use Yangweijie\Ui2\Rendering\StrokeCircle;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn search field: a text box (surface fill + primary border when
 * focused) with a leading magnifier glyph, the value/placeholder text, and a
 * trailing clear "×" button drawn when {@see SearchFieldSpec::$showClear} is set.
 */
final class SearchFieldRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'search_field';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof SearchFieldSpec) {
            throw new \InvalidArgumentException('SearchFieldRenderer requires a SearchFieldSpec');
        }

        $bg = $tokens->color('color.surface');
        $border = $spec->focused ? $tokens->color('color.primary') : $tokens->color('color.track');

        $commands = [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $bg),
            new StrokeRoundedRect(0.75, 0.75, $width - 1.5, $height - 1.5, $spec->radius, $border, StrokeParams::solid(1.5)),
        ];

        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $onSurface = $tokens->color('color.onSurface');
        $muted = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.5);

        // Leading magnifier glyph.
        $cx = 13.0;
        $cy = $height / 2;
        $r = min($height * 0.18, 6.0);
        $iconColor = $spec->focused ? $tokens->color('color.primary') : $muted;
        $commands[] = new StrokeCircle($cx, $cy, $r, $iconColor, StrokeParams::solid(1.5));
        $commands[] = new StrokeLine($cx + $r * 0.7, $cy + $r * 0.7, $cx + $r * 1.5, $cy + $r * 1.5, $iconColor, 1.5);

        // Value / placeholder text.
        $text = $spec->value !== '' ? $spec->value : $spec->placeholder;
        if ($text !== '') {
            $isPlaceholder = $spec->value === '';
            $color = $isPlaceholder ? $muted : ($spec->enabled ? $onSurface : Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4));
            $fontSize = min($height * 0.5, 14.0);
            $font = $tokens->font($fontSize);
            $str = new AttributedString();
            $str->append($text, Attribute::fromColor($color), Attribute::size($fontSize));
            $layout = new TextLayout($str, $font, $width - 44, DrawTextAlign::Left);
            [, $th] = $layout->extents();
            $commands[] = new DrawText($layout, 26, ($height - $th) / 2);
        }

        // Trailing clear button.
        if ($spec->showClear && $spec->value !== '') {
            $bx = $width - 14.0;
            $by = $height / 2;
            $s = 4.0;
            $commands[] = new StrokeLine($bx - $s, $by - $s, $bx + $s, $by + $s, $muted, 1.5);
            $commands[] = new StrokeLine($bx + $s, $by - $s, $bx - $s, $by + $s, $muted, 1.5);
        }

        return new RenderCommandList($commands);
    }
}
