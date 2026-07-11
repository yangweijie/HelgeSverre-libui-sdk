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
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn checkbox: a rounded box with a two-stroke checkmark when checked.
 *
 * Hover/disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait) so it matches ButtonRenderer exactly.
 */
final class CheckboxRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'checkbox';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof CheckboxSpec) {
            throw new \InvalidArgumentException('CheckboxRenderer requires a CheckboxSpec');
        }

        $box = min($height, 18.0);
        $ox = 0.0;
        $oy = ($height - $box) / 2;
        $primary = $this->paint($spec, $tokens, 'color.primary');
        $track = $tokens->color('color.track');

        $commands = [];
        if ($spec->checked) {
            $commands[] = new FillRoundedRect($ox, $oy, $box, $box, $spec->radius, $primary);
        } else {
            $commands[] = new FillRoundedRect($ox, $oy, $box, $box, $spec->radius, $track);
        }
        $commands[] = new StrokeRoundedRect($ox, $oy, $box, $box, $spec->radius, $primary, StrokeParams::solid(1.5));

        if ($spec->checked) {
            $surface = $tokens->color('color.surface');
            // Two-segment checkmark inside the box.
            $commands[] = new StrokeLine($ox + $box * 0.2, $oy + $box * 0.55, $ox + $box * 0.45, $oy + $box * 0.75, $surface, 2.0);
            $commands[] = new StrokeLine($ox + $box * 0.45, $oy + $box * 0.75, $ox + $box * 0.8, $oy + $box * 0.3, $surface, 2.0);
        }

        // Token-driven hover/disabled wash over the whole control row.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        if ($spec->label !== '') {
            $box = min($height, 18.0);
            $fontSize = min($height * 0.5, 14.0);
            $font = new FontDescriptor('Arial', $fontSize);
            $str = new AttributedString();
            $str->append($spec->label, Attribute::fromColor($this->paint($spec, $tokens, 'color.onSurface')), Attribute::size($fontSize));
            $layout = new TextLayout($str, $font, $width - $box - 8, DrawTextAlign::Left);
            [$tw, $th] = $layout->extents();
            $commands[] = new DrawText($layout, $box + 8, ($height - $th) / 2);
        }

        return new RenderCommandList($commands);
    }

    private function paint(CheckboxSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);
        if (! $spec->enabled) {
            return Color::rgba($c->r, $c->g, $c->b, 0.4);
        }

        return $c;
    }
}
