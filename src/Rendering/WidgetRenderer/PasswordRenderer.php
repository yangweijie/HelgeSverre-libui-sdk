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
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;

/**
 * Self-drawn password field: identical geometry to {@see TextFieldRenderer},
 * but the value is masked with bullet glyphs unless {@see PasswordSpec::$reveal}
 * is set (a peek/eye toggle handled by the host control).
 */
final class PasswordRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'password_field';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof PasswordSpec) {
            throw new \InvalidArgumentException('PasswordRenderer requires a PasswordSpec');
        }

        $bg = $tokens->color('color.surface');
        $border = $spec->focused
            ? $this->paint($spec, $tokens, 'color.primary')
            : $tokens->color('color.track');

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

        if ($spec->value === '') {
            if ($spec->placeholder === '') {
                return new RenderCommandList($commands);
            }
            $text = $spec->placeholder;
            $isPlaceholder = true;
        } else {
            $text = $spec->reveal
                ? $spec->value
                : str_repeat('•', mb_strlen($spec->value, 'UTF-8'));
            $isPlaceholder = false;
        }

        $onSurface = $tokens->color('color.onSurface');
        $color = $isPlaceholder ? Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.5) : $onSurface;
        if (! $spec->enabled) {
            $color = Color::rgba($color->r, $color->g, $color->b, 0.4);
        }

        $fontSize = min($height * 0.5, 14.0);
        $font = $tokens->font($fontSize);
        $str = new AttributedString();
        $str->append($text, Attribute::fromColor($color), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, $width - 16, DrawTextAlign::Left);
        [, $th] = $layout->extents();
        $commands[] = new DrawText($layout, 8, ($height - $th) / 2);

        return new RenderCommandList($commands);
    }

    private function paint(PasswordSpec $spec, DesignTokens $tokens, string $path): Color
    {
        $c = $tokens->color($path);

        return $spec->enabled ? $c : Color::rgba($c->r, $c->g, $c->b, 0.4);
    }
}
