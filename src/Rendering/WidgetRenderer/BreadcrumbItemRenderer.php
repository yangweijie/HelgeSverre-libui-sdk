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
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Self-drawn breadcrumb item: a left-aligned label (current crumb in primary,
 * others muted) with a token hover wash and an optional trailing separator.
 */
final class BreadcrumbItemRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'breadcrumb_item';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof BreadcrumbItemSpec) {
            throw new \InvalidArgumentException('BreadcrumbItemRenderer requires a BreadcrumbItemSpec');
        }

        return $this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius);
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $onSurface = $tokens->color('color.onSurface');
        if (! $spec->enabled || ! $spec->active) {
            $onSurface = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, $spec->active ? 1.0 : 0.6);
        }
        $color = $spec->active ? $tokens->color('color.primary') : $onSurface;

        $fontSize = min($height * 0.5, 13.0);
        $font = $tokens->font($fontSize);
        $str = new AttributedString();
        $str->append($spec->label, Attribute::fromColor($color), Attribute::size($fontSize));
        $layout = new TextLayout($str, $font, $width, DrawTextAlign::Left);
        [, $th] = $layout->extents();
        $commands[] = new DrawText($layout, 8, ($height - $th) / 2);

        if (! $spec->isLast && $spec->separator !== '') {
            $sep = new AttributedString();
            $muted = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.5);
            $sep->append($spec->separator, Attribute::fromColor($muted), Attribute::size($fontSize));
            $sepLayout = new TextLayout($sep, $font, $width, DrawTextAlign::Left);
            [$sw] = $sepLayout->extents();
            $commands[] = new DrawText($sepLayout, $width - $sw - 4, ($height - $th) / 2);
        }

        return new RenderCommandList($commands);
    }
}
