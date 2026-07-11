<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn push button renderer.
 *
 * Variants:
 *  - filled  : solid primary background, surface (light) text
 *  - soft    : track-coloured background, primary text + primary hairline
 *  - outline : no fill, primary hairline + primary text
 *  - disabled: track background, muted onSurface text, no border
 *
 * A pressed state darkens the primary-derived colours by 15% for feedback.
 * The background/geometry is pure (headless-safe via shapeCommands()); the
 * label is layered on in render() via a DrawText command. Hover/disabled wash
 * is consumed from the token system via the shared {@see TokenWash} trait.
 */
final class ButtonRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'button';
    }

    /**
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ButtonSpec) {
            throw new \InvalidArgumentException('ButtonRenderer requires a ButtonSpec');
        }

        $commands = [];

        $bg = $this->background($spec, $tokens);
        if ($bg !== null) {
            $commands[] = new FillRoundedRect(0.0, 0.0, $width, $height, $spec->radius, $bg);
        }

        $border = $this->border($spec, $tokens);
        if ($border !== null) {
            $inset = 0.75;
            $commands[] = new StrokeRoundedRect(
                $inset,
                $inset,
                $width - 2 * $inset,
                $height - 2 * $inset,
                $spec->radius,
                $border,
                StrokeParams::solid(1.5),
            );
        }

        // Interaction-state washes (Phase 10): a token-driven alpha overlay on
        // top of the background. The theme decides whether the wash darkens
        // (light) or lightens (dark) — the renderer just consumes the token.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof ButtonSpec) {
            throw new \InvalidArgumentException('ButtonRenderer requires a ButtonSpec');
        }

        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $label = $spec->label;
        if ($label !== '') {
            $fontSize = min($height * 0.42, 16.0);
            $font = new FontDescriptor('Arial', $fontSize);
            $str = new AttributedString();
            $str->append($label, Attribute::fromColor($this->textColor($spec, $tokens)), Attribute::size($fontSize));

            $layout = new TextLayout($str, $font, $width, DrawTextAlign::Left);
            [$tw, $th] = $layout->extents();
            $commands[] = new DrawText($layout, ($width - $tw) / 2, ($height - $th) / 2);
        }

        return new RenderCommandList($commands);
    }

    private function background(ButtonSpec $spec, DesignTokens $tokens): ?Color
    {
        if (! $spec->enabled) {
            return $tokens->color('color.track');
        }

        return match ($spec->variant) {
            'soft'    => $tokens->color('color.track'),
            'outline' => null,
            default   => $this->darken($tokens->color('color.primary'), $spec->pressed),
        };
    }

    private function border(ButtonSpec $spec, DesignTokens $tokens): ?Color
    {
        if (! $spec->enabled) {
            return null;
        }

        return match ($spec->variant) {
            'soft'    => $tokens->color('color.primary'),
            'outline' => $this->darken($tokens->color('color.primary'), $spec->pressed),
            default   => null,
        };
    }

    private function textColor(ButtonSpec $spec, DesignTokens $tokens): Color
    {
        if (! $spec->enabled) {
            $on = $tokens->color('color.onSurface');

            return Color::rgba($on->r, $on->g, $on->b, 0.5);
        }

        return match ($spec->variant) {
            'filled'  => $tokens->color('color.surface'),
            'soft'    => $tokens->color('color.primary'),
            'outline' => $tokens->color('color.primary'),
            default   => $tokens->color('color.surface'),
        };
    }

    private function darken(Color $c, bool $pressed): Color
    {
        if (! $pressed) {
            return $c;
        }

        return Color::rgba($c->r * 0.85, $c->g * 0.85, $c->b * 0.85, $c->a);
    }
}
