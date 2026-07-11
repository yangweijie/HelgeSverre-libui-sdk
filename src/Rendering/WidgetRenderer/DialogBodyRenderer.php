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
 * Self-drawn dialog title + message: a larger primary-onSurface heading and a
 * wrapped, slightly muted body paragraph. Pure text, so render() is
 * headless-safe (no extra geometry).
 */
final class DialogBodyRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'dialog_body';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        return []; // text only — no geometry
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof DialogBodySpec) {
            throw new \InvalidArgumentException('DialogBodyRenderer requires a DialogBodySpec');
        }

        $commands = [];
        $onSurface = $tokens->color('color.onSurface');
        $muted = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.7);

        $padX = 2.0;
        $titleSize = min($height * 0.16, 18.0);
        $titleFont = new FontDescriptor('Arial', $titleSize);
        $titleStr = new AttributedString();
        $titleStr->append($spec->title, Attribute::fromColor($onSurface), Attribute::size($titleSize));
        $titleLayout = new TextLayout($titleStr, $titleFont, $width - $padX * 2, DrawTextAlign::Left);
        [, $th] = $titleLayout->extents();
        $titleY = max(0.0, ($height - $th) / 2 - $height * 0.18);
        $commands[] = new DrawText($titleLayout, $padX, $titleY);

        if ($spec->message !== '') {
            $msgSize = min($height * 0.13, 14.0);
            $msgFont = new FontDescriptor('Arial', $msgSize);
            $msgStr = new AttributedString();
            $msgStr->append($spec->message, Attribute::fromColor($muted), Attribute::size($msgSize));
            $msgLayout = new TextLayout($msgStr, $msgFont, $width - $padX * 2, DrawTextAlign::Left);
            [, $mh] = $msgLayout->extents();
            $msgY = $titleY + $th + 8;
            $commands[] = new DrawText($msgLayout, $padX, $msgY);
        }

        return new RenderCommandList($commands);
    }
}
