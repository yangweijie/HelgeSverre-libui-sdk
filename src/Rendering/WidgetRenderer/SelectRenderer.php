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
use Yangweijie\Ui2\Rendering\FillPolygon;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn select box: a surface-filled rounded box with a border, the value
 * (or placeholder) text, and a small downward caret polygon on the right.
 *
 * Hover/disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait) so it matches ButtonRenderer exactly.
 */
final class SelectRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'select';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof SelectSpec) {
            throw new \InvalidArgumentException('SelectRenderer requires a SelectSpec');
        }

        $bg = $tokens->color('color.surface');
        $border = $tokens->color('color.track');
        $onSurface = $tokens->color('color.onSurface');
        $caret = $spec->enabled ? $onSurface : Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4);

        $commands = [
            new FillRoundedRect(0, 0, $width, $height, $spec->radius, $bg),
            new StrokeRoundedRect(0.75, 0.75, $width - 1.5, $height - 1.5, $spec->radius, $border, StrokeParams::solid(1.5)),
        ];

        // Downward caret triangle near the right edge.
        $cx = $width - 14;
        $cy = $height / 2;
        $s = min($height * 0.2, 5.0);
        $commands[] = new FillPolygon(
            [[$cx - $s, $cy - $s / 2], [$cx + $s, $cy - $s / 2], [$cx, $cy + $s]],
            $caret,
        );

        // Token-driven hover/disabled wash over the whole box.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $text = $spec->value !== '' ? $spec->value : $spec->placeholder;
        $isPlaceholder = $spec->value === '';
        $onSurface = $tokens->color('color.onSurface');
        $color = $isPlaceholder ? Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.5) : $onSurface;
        if (! $spec->enabled) {
            $color = Color::rgba($color->r, $color->g, $color->b, 0.4);
        }

        $fontSize = min($height * 0.5, 14.0);
        $font = $tokens->font($fontSize);
        $str = new AttributedString();
        $str->append($text, Attribute::fromColor($color), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, $width - 30, DrawTextAlign::Left);
        [, $th] = $layout->extents();
        $commands[] = new DrawText($layout, 10, ($height - $th) / 2);

        return new RenderCommandList($commands);
    }
}
