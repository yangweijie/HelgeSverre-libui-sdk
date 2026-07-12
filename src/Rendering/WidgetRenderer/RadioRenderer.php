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
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeCircle;

/**
 * Self-drawn radio button: a primary outer ring with an inner dot when selected.
 *
 * Hover/disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait) so it matches ButtonRenderer exactly.
 */
final class RadioRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'radio';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof RadioSpec) {
            throw new \InvalidArgumentException('RadioRenderer requires a RadioSpec');
        }

        $r = min($height, 18.0) / 2;
        $cx = $r;
        $cy = $height / 2;
        $primary = $this->paint($spec, $tokens, 'color.primary');
        $track = $tokens->color('color.track');

        $commands = [];
        $commands[] = new StrokeCircle($cx, $cy, $r, $primary, StrokeParams::solid(1.5));
        if ($spec->selected) {
            $commands[] = new FillCircle($cx, $cy, $r * 0.5, $primary);
        } else {
            $commands[] = new FillCircle($cx, $cy, $r * 0.5, $track);
        }

        // Token-driven hover/disabled wash over the whole control row (pill radius).
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $height / 2) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        if ($spec->label !== '') {
            $diameter = min($height, 18.0);
            $fontSize = min($height * 0.5, 14.0);
            $font = $tokens->font($fontSize);
            $str = new AttributedString();
            $str->append($spec->label, Attribute::fromColor($this->paint($spec, $tokens, 'color.onSurface')), Attribute::size($fontSize));
            $layout = new TextLayout($str, $font, $width - $diameter - 8, DrawTextAlign::Left);
            [, $th] = $layout->extents();
            $commands[] = new DrawText($layout, $diameter + 8, ($height - $th) / 2);
        }

        return new RenderCommandList($commands);
    }

    private function paint(RadioSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);

        return $spec->enabled ? $c : Color::rgba($c->r, $c->g, $c->b, 0.4);
    }
}
