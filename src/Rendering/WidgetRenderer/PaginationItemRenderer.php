<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Libui\Color;
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

/**
 * Self-drawn pagination token: a centred page number / glyph. The active page
 * gets a filled primary chip; prev/next/page tokens get a token hover wash;
 * the gap token ("…") is drawn muted and is non-interactive.
 */
final class PaginationItemRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'pagination_item';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof PaginationItemSpec) {
            throw new \InvalidArgumentException('PaginationItemRenderer requires a PaginationItemSpec');
        }

        $commands = [];
        if ($spec->kind === 'gap') {
            return $commands;
        }

        if ($spec->active) {
            $commands[] = new FillRoundedRect(2, 2, $width - 4, $height - 4, $spec->radius, $tokens->color('color.primary'));
        } else {
            foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
                $commands[] = $washCmd;
            }
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $onSurface = $tokens->color('color.onSurface');
        if (! $spec->enabled && $spec->kind !== 'gap') {
            $onSurface = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.35);
        }
        $color = $spec->active ? $tokens->color('color.onPrimary') : $onSurface;

        $fontSize = min($height * 0.4, 13.0);
        $font = new FontDescriptor('Arial', $fontSize);
        $str = new AttributedString();
        $str->append($spec->label, Attribute::fromColor($color), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, $width, DrawTextAlign::Center);
        [$tw, $th] = $layout->extents();
        $commands[] = new DrawText($layout, ($width - $tw) / 2, ($height - $th) / 2);

        return new RenderCommandList($commands);
    }
}
