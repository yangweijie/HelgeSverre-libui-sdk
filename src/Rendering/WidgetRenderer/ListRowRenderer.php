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
use Yangweijie\Ui2\Rendering\StrokeLine;

/**
 * Self-drawn list row: a selectable row with a primary label (and an optional
 * secondary subtitle), a bottom hairline separator, and a selection highlight.
 *
 * Hover / disabled feedback is consumed from the token system (shared
 * {@see TokenWash} trait); a selected row additionally paints a
 * color.selection tint so the active row reads as "picked" exactly like a
 * native listbox selection.
 */
final class ListRowRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'list_row';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ListRowSpec) {
            throw new \InvalidArgumentException('ListRowRenderer requires a ListRowSpec');
        }

        $commands = [];

        if ($spec->selected) {
            $commands[] = new FillRoundedRect(0, 0, $width, $height, $spec->radius, $tokens->selection());
        }

        // Token-driven hover/disabled wash on top of the (selected) background.
        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        // Bottom hairline separator (skipped when the row is the selection tint,
        // so the highlight reads as a single clean block).
        if (! $spec->selected) {
            $track = $tokens->color('color.track');
            $commands[] = new StrokeLine(0, $height - 0.5, $width, $height - 0.5, $track, 1.0);
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof ListRowSpec) {
            throw new \InvalidArgumentException('ListRowRenderer requires a ListRowSpec');
        }

        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $onSurface = $tokens->color('color.onSurface');
        $muted = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.6);
        if (! $spec->enabled) {
            $onSurface = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4);
            $muted = $onSurface;
        }

        $padX = 12.0;
        $labelSize = min($height * 0.42, 14.0);
        $labelFont = $tokens->font($labelSize);
        $labelStr = new AttributedString();
        $labelStr->append($spec->label, Attribute::fromColor($onSurface), Attribute::size($labelSize));
        $labelLayout = new TextLayout($labelStr, $labelFont, $width - $padX * 2, DrawTextAlign::Left);
        [, $lh] = $labelLayout->extents();

        if ($spec->subtitle !== '') {
            $subSize = min($height * 0.34, 12.0);
            $subFont = $tokens->font($subSize);
            $subStr = new AttributedString();
            $subStr->append($spec->subtitle, Attribute::fromColor($muted), Attribute::size($subSize));
            $subLayout = new TextLayout($subStr, $subFont, $width - $padX * 2, DrawTextAlign::Left);
            [, $sh] = $subLayout->extents();
            $blockH = $lh + 3 + $sh;
            $top = ($height - $blockH) / 2;
            $commands[] = new DrawText($labelLayout, $padX, $top);
            $commands[] = new DrawText($subLayout, $padX, $top + $lh + 3);
        } else {
            $commands[] = new DrawText($labelLayout, $padX, ($height - $lh) / 2);
        }

        return new RenderCommandList($commands);
    }
}
