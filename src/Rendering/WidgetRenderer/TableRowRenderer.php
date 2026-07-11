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
use Yangweijie\Ui2\Rendering\StrokeLine;

/**
 * Self-drawn table row: lays its cells out across columns described by the
 * spec's relative widths, with a selection highlight and per-row hover/disabled
 * wash from the token system ({@see TokenWash}).
 *
 * The header row is drawn with a heavier weight and a stronger bottom rule so
 * it reads as a header; data rows get a hairline between them.
 */
final class TableRowRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'table_row';
    }

    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof TableRowSpec) {
            throw new \InvalidArgumentException('TableRowRenderer requires a TableRowSpec');
        }

        $commands = [];

        if ($spec->selected) {
            $commands[] = new FillRoundedRect(0, 0, $width, $height, $spec->radius, $tokens->selection());
        }

        foreach ($this->washCommands($spec->enabled, $spec->hovered, $tokens, $width, $height, $spec->radius) as $washCmd) {
            $commands[] = $washCmd;
        }

        $track = $tokens->color('color.track');
        if ($spec->header) {
            // Stronger rule under the header.
            $commands[] = new StrokeLine(0, $height - 0.5, $width, $height - 0.5, $track, 1.5);
        } elseif (! $spec->selected) {
            // Hairline between data rows.
            $commands[] = new StrokeLine(0, $height - 0.5, $width, $height - 0.5, $track, 1.0);
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof TableRowSpec) {
            throw new \InvalidArgumentException('TableRowRenderer requires a TableRowSpec');
        }

        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $onSurface = $tokens->color('color.onSurface');
        if (! $spec->enabled) {
            $onSurface = Color::rgba($onSurface->r, $onSurface->g, $onSurface->b, 0.4);
        }

        $xs = $this->columnOffsets($spec, $width);
        $padX = 10.0;
        $fontSize = min($height * ($spec->header ? 0.46 : 0.42), $spec->header ? 14.0 : 13.0);

        foreach ($spec->cells as $i => $text) {
            $colW = ($xs[$i + 1] ?? $width) - $xs[$i];
            if ($colW <= $padX * 2) {
                continue;
            }
            $font = new FontDescriptor('Arial', $fontSize);
            $str = new AttributedString();
            $str->append((string) $text, Attribute::fromColor($onSurface), Attribute::size($fontSize));
            $layout = new TextLayout($str, $font, $colW - $padX * 2, DrawTextAlign::Left);
            [, $th] = $layout->extents();
            $commands[] = new DrawText($layout, $xs[$i] + $padX, ($height - $th) / 2);
        }

        return new RenderCommandList($commands);
    }

    /**
     * @return list<float> x offsets for each column boundary (count = cells+1).
     */
    private function columnOffsets(TableRowSpec $spec, float $width): array
    {
        $total = array_sum($spec->widths) ?: 1.0;
        $xs = [0.0];
        $acc = 0.0;
        foreach ($spec->widths as $w) {
            $acc += ($w / $total) * $width;
            $xs[] = $acc;
        }

        return $xs;
    }
}
