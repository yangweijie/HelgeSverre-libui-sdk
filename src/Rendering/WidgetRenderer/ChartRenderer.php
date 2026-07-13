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
use Yangweijie\Ui2\ChartV2\ChartData;
use Yangweijie\Ui2\ChartV2\ChartSeries;
use Yangweijie\Ui2\ChartV2\Scale;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillArc;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillPolygon;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeArc;
use Yangweijie\Ui2\Rendering\StrokeCircle;
use Yangweijie\Ui2\Rendering\StrokeLine;

/**
 * Self-drawn chart renderer — emits RenderCommand[] from ChartSpec.
 *
 * Supports: line, bar, area, pie, doughnut, scatter.
 * Pure geometry generation; no Area dependency.
 */
final class ChartRenderer implements WidgetRenderer
{
    use TokenWash;

    public static function type(): string
    {
        return 'chart';
    }

    /**
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ChartSpec) {
            throw new \InvalidArgumentException('ChartRenderer requires a ChartSpec');
        }

        $commands = [];
        $data = $spec->data;
        $type = $data->type;

        // Background
        $commands[] = new FillRoundedRect(0.0, 0.0, $width, $height, 8.0, $this->getBackgroundColor($data, $tokens));

        // Compute plot area
        $plot = $this->computePlotArea($width, $height, $data, $tokens);

        if ($type === 'pie' || $type === 'doughnut') {
            $commands = array_merge($commands, $this->drawPie($spec, $plot, $data, $tokens));
        } else {
            // Grid, axes, ticks
            $commands = array_merge($commands, $this->drawGridAxes($spec, $plot, $data, $tokens));

            // Render each series
            foreach ($data->series as $series) {
                $commands = array_merge($commands, $this->drawSeries($spec, $plot, $series, $data, $tokens));
            }
        }

        return $commands;
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        if (! $spec instanceof ChartSpec) {
            throw new \InvalidArgumentException('ChartRenderer requires a ChartSpec');
        }

        $commands = $this->shapeCommands($spec, $tokens, $width, $height);

        $data = $spec->data;

        // Title
        if ($data->showTitle && $data->title !== '') {
            $commands[] = $this->drawTitle($spec, $tokens, $width);
        }

        // Legend
        if ($data->showLegend) {
            $commands = array_merge($commands, $this->drawLegend($spec, $tokens, $width, $height));
        }

        return new RenderCommandList($commands);
    }

    /** @return array<float,float,float,float> [x, y, w, h] */
    private function computePlotArea(float $width, float $height, ChartData $data, DesignTokens $tokens): array
    {
        $pad = $data->padding;
        $plotX = $pad[3]; // left
        $plotY = $pad[0]; // top

        if ($data->showTitle && $data->title !== '') {
            $plotY += 28.0;
        }

        $plotW = $width - $pad[3] - $pad[2]; // left - right
        $plotH = $height - $pad[0] - $pad[1]; // top - bottom

        // Reserve space for legend
        if ($data->showLegend) {
            $legendCount = count($data->series);
            if ($data->legendPosition === 'right' && $legendCount > 0) {
                $plotW -= 120.0;
            } elseif (in_array($data->legendPosition, ['top', 'bottom']) && $legendCount > 0) {
                $plotH -= 16.0 * max(1, $legendCount);
            }
        }

        // Reserve space for X-axis labels
        if ($data->showTicks) {
            $plotH -= 16.0;
        }

        // Reserve space for Y-axis labels
        if ($data->showTicks) {
            $plotW -= 40.0;
        }

        $plotW = max(50.0, $plotW);
        $plotH = max(50.0, $plotH);

        return [$plotX, $plotY, $plotW, $plotH];
    }

    /** @return list<RenderCommand> */
    private function drawGridAxes(ChartSpec $spec, array $plot, ChartData $data, DesignTokens $tokens): array
    {
        $commands = [];
        $font = $this->fontFromTokens($tokens, 10.0);

        // Compute Y-axis ticks
        [$yMin, $yMax] = Scale::computeDomain($data->series);
        $yTicks = Scale::niceTicks($yMin, $yMax, $data->yTicks);

        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];

        // Y-axis ticks and grid lines
        $gridColor = $this->getGridColor($data, $tokens);
        $axisColor = $this->getAxisColor($data, $tokens);
        $textColor = $this->getTextColor($data, $tokens);

        foreach ($yTicks['ticks'] as $tick) {
            $yPx = $plotY + $plotH - Scale::mapValue($tick, $yTicks['min'], $yTicks['max'], $plotY, $plotY + $plotH);

            // Grid line
            if ($data->showGrid) {
                $commands[] = new StrokeLine($plotX, $yPx, $plotX + $plotW, $yPx, $gridColor, 1.0);
            }

            // Tick label
            if ($data->showTicks) {
                $label = Scale::formatTick($tick);
                $commands[] = $this->drawTextCommand($label, $font, $plotX - 36.0, $yPx + 4.0, $textColor, DrawTextAlign::Right);
            }
        }

        // X-axis line
        if ($data->showAxes) {
            $commands[] = new StrokeLine($plotX, $plotY + $plotH, $plotX + $plotW, $plotY + $plotH, $axisColor, 1.5);
        }

        // Y-axis line
        if ($data->showAxes) {
            $commands[] = new StrokeLine($plotX, $plotY, $plotX, $plotY + $plotH, $axisColor, 1.5);
        }

        // X-axis tick labels
        if ($data->showTicks && !empty($data->labels)) {
            $labelCount = count($data->labels);
            $step = max(1, (int) ceil($labelCount / $data->xTicks));

            foreach ($data->labels as $i => $label) {
                if ($i % $step !== 0) {
                    continue;
                }
                $xPx = $plotX + ($i / max(1, $labelCount - 1)) * $plotW;
                $commands[] = $this->drawTextCommand($label, $font, $xPx, $plotY + $plotH + 16.0, $textColor, DrawTextAlign::Center);
            }
        }

        return $commands;
    }

    /** @return list<RenderCommand> */
    private function drawSeries(ChartSpec $spec, array $plot, ChartSeries $series, ChartData $data, DesignTokens $tokens): array
    {
        $commands = [];
        $color = Color::rgb((int) ($series->color ?? $this->getPaletteColor($spec, $data, $tokens)));

        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];

        // Compute domain
        [$yMin, $yMax] = Scale::computeDomain([$series]);
        if ($series->type === 'pie' || $series->type === 'doughnut') {
            // For pie/doughnut, use the series values directly
            return $commands;
        }

        $values = $series->values();
        $labelCount = count($data->labels);
        $pointCount = count($values);
        $effectiveCount = max($labelCount, $pointCount);

        // Determine the type from the data model
        $seriesType = $data->type;

        match ($seriesType) {
            'line' => $this->drawLineSeries($commands, $series, $plot, $yMin, $yMax, $color, $data, $tokens),
            'bar'  => $this->drawBarSeries($commands, $series, $plot, $yMin, $yMax, $color, $effectiveCount, $data, $tokens),
            'area' => $this->drawAreaSeries($commands, $series, $plot, $yMin, $yMax, $color, $data, $tokens),
            'scatter' => $this->drawScatterSeries($commands, $series, $plot, $yMin, $yMax, $color, $data, $tokens),
            default => null,
        };

        return $commands;
    }

    /** @return list<RenderCommand> */
    private function drawLineSeries(array &$commands, ChartSeries $series, array $plot, float $yMin, float $yMax, Color $color, ChartData $data, DesignTokens $tokens): void
    {
        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];
        $values = $series->values();

        $prevX = null;
        $prevY = null;

        foreach ($values as $i => $v) {
            $xPx = $plotX + ($i / max(1, count($values) - 1)) * $plotW;
            $yPx = $plotY + $plotH - Scale::mapValue($v, $yMin, $yMax, $plotY, $plotY + $plotH);

            // Line segment
            if ($prevX !== null) {
                $commands[] = new StrokeLine($prevX, $prevY, $xPx, $yPx, $color, $series->lineWidth);
            }

            // Point
            if ($series->showPoints) {
                $commands[] = new FillCircle($xPx, $yPx, $series->pointRadius, $color);
            }

            // Value label above point
            if ($data->showValueLabels) {
                $labelText = Scale::formatTick($v, 0);
                $labelFont = $this->fontFromTokens($tokens, 10.0);
                $textColor = $this->getTextColor($data, $tokens);
                $commands[] = $this->drawTextCommand($labelText, $labelFont, $xPx, $yPx - 14.0, $textColor, DrawTextAlign::Center);
            }

            $prevX = $xPx;
            $prevY = $yPx;
        }
    }

    /** @return list<RenderCommand> */
    private function drawBarSeries(array &$commands, ChartSeries $series, array $plot, float $yMin, float $yMax, Color $color, int $effectiveCount, ChartData $data, DesignTokens $tokens): void
    {
        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];
        $values = $series->values();

        $barSlotWidth = $plotW / max(1, $effectiveCount);
        $barWidth = min($series->barWidth, $barSlotWidth - $series->barGap);

        foreach ($values as $i => $v) {
            $barX = $plotX + $i * $barSlotWidth + ($barSlotWidth - $barWidth) / 2;

            if ($v < 0) {
                $barY = $plotY + $plotH;
                $barH = Scale::mapValue($v, $yMin, 0.0, $plotY + $plotH, $plotY);
                $barH = abs($barH);
            } else {
                $barY = $plotY + $plotH - Scale::mapValue($v, $yMin, $yMax, $plotY, $plotY + $plotH);
                $barH = Scale::mapValue($yMax, $yMin, $yMax, $plotY, $plotY + $plotH) - $barY;
                if ($barH < 0) $barH = 0;
            }

            $barY = $plotY + $plotH - Scale::mapValue($v, $yMin, $yMax, $plotY, $plotY + $plotH);
            $barH = max(0.0, Scale::mapValue($yMax, $yMin, $yMax, $plotY, $plotY + $plotH) - $barY);

            // For negative values, the bar goes down from baseline
            if ($v < 0) {
                $barY = $plotY + $plotH;
                $barH = Scale::mapValue($v, $yMin, 0.0, $plotY + $plotH, $plotY);
                $barH = abs($barH);
            }

            $commands[] = new FillRoundedRect($barX, $barY, $barWidth, $barH, 4.0, $color);

            // Value label above bar
            if ($data->showValueLabels) {
                $labelText = Scale::formatTick($v, 0);
                $labelFont = $this->fontFromTokens($tokens, 10.0);
                $textColor = $this->getTextColor($data, $tokens);
                $commands[] = $this->drawTextCommand($labelText, $labelFont, $barX + $barWidth / 2.0, $barY - 14.0, $textColor, DrawTextAlign::Center);
            }
        }
    }

    /** @return list<RenderCommand> */
    private function drawAreaSeries(array &$commands, ChartSeries $series, array $plot, float $yMin, float $yMax, Color $color, ChartData $data, DesignTokens $tokens): void
    {
        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];
        $values = $series->values();

        // Build polygon points for the filled area
        $points = [];
        $baseline = $plotY + $plotH;

        // Top edge (data line)
        foreach ($values as $i => $v) {
            $xPx = $plotX + ($i / max(1, count($values) - 1)) * $plotW;
            $yPx = $plotY + $plotH - Scale::mapValue($v, $yMin, $yMax, $plotY, $plotY + $plotH);
            $points[] = [$xPx, $yPx];
        }

        // Bottom edge (baseline) — right to left
        foreach (array_reverse($values) as $i => $v) {
            $xPx = $plotX + (count($values) - 1 - $i) / max(1, count($values) - 1) * $plotW;
            $points[] = [$xPx, $baseline];
        }

        if (count($points) >= 3) {
            // Create a translucent fill color
            $fillColor = $this->alphaColor($color, $series->fillOpacity);
            $commands[] = new FillPolygon($points, $fillColor);
        }

        // Draw the line on top
        $this->drawLineSeries($commands, $series, $plot, $yMin, $yMax, $color, $data, $tokens);
    }

    /** @return list<RenderCommand> */
    private function drawScatterSeries(array &$commands, ChartSeries $series, array $plot, float $yMin, float $yMax, Color $color, ChartData $data, DesignTokens $tokens): void
    {
        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];
        $values = $series->values();

        foreach ($values as $i => $v) {
            $xPx = $plotX + ($i / max(1, count($values) - 1)) * $plotW;
            $yPx = $plotY + $plotH - Scale::mapValue($v, $yMin, $yMax, $plotY, $plotY + $plotH);

            $commands[] = new FillCircle($xPx, $yPx, $series->pointRadius, $color);
            $commands[] = new StrokeCircle($xPx, $yPx, $series->pointRadius, $color, StrokeParams::solid(1.5));

            // Value label above point
            if ($data->showValueLabels) {
                $labelText = Scale::formatTick($v, 0);
                $labelFont = $this->fontFromTokens($tokens, 10.0);
                $textColor = $this->getTextColor($data, $tokens);
                $commands[] = $this->drawTextCommand($labelText, $labelFont, $xPx, $yPx - 14.0, $textColor, DrawTextAlign::Center);
            }
        }
    }

    /** @return list<RenderCommand> */
    private function drawPie(ChartSpec $spec, array $plot, ChartData $data, DesignTokens $tokens): array
    {
        $commands = [];

        $plotX = $plot[0];
        $plotY = $plot[1];
        $plotW = $plot[2];
        $plotH = $plot[3];

        $cx = $plotX + $plotW / 2;
        $cy = $plotY + $plotH / 2;
        $radius = min($plotW, $plotH) / 2 - 16.0;

        // Compute pie slice angles from the first series values
        $series = $data->series[0] ?? null;
        if ($series === null) {
            return $commands;
        }

        $values = $series->values();
        $total = array_sum(array_filter($values, fn($v) => $v > 0));
        if ($total === 0.0) {
            return $commands;
        }

        $startAngle = -M_PI / 2; // Start from top
        $innerRadius = 0.0;

        if ($data->type === 'doughnut') {
            $innerRadius = $radius * 0.5;
        }

        foreach ($values as $i => $v) {
            if ($v <= 0) {
                continue;
            }

            $angle = ($v / $total) * 2 * M_PI;
            $sliceColor = Color::rgb((int) ($series->color ?? $this->getPaletteColor($spec, $data, $tokens, $i)));

            if ($data->type === 'doughnut') {
                // Outer arc
                $commands[] = new StrokeArc(
                    $cx, $cy, $radius,
                    $startAngle, $angle,
                    $sliceColor, StrokeParams::solid(max(1.0, $radius * 2)),
                );
            } else {
                // Wedge (pie slice)
                $commands[] = new FillArc($cx, $cy, $radius, $startAngle, $angle, $sliceColor, wedge: true);
            }

            $startAngle += $angle;
        }

        return $commands;
    }

    /** Draw title as a DrawText command */
    private function drawTitle(ChartSpec $spec, DesignTokens $tokens, float $width): DrawText
    {
        $data = $spec->data;
        $font = new FontDescriptor($data->fontFamily, 16.0, \Libui\Generated\Enum\TextWeight::Bold);
        $textColor = $this->getTextColor($data, $tokens);

        $str = new AttributedString();
        $str->append($data->title, Attribute::fromColor($textColor), Attribute::size(16.0));

        $layout = new TextLayout($str, $font, $width, DrawTextAlign::Left);
        [$tw, $th] = $layout->extents();

        return new DrawText($layout, ($width - $tw) / 2, 12.0);
    }

    /** @return list<RenderCommand> */
    private function drawLegend(ChartSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        $commands = [];
        $data = $spec->data;
        $font = new FontDescriptor($data->fontFamily, 11.0, \Libui\Generated\Enum\TextWeight::Medium);
        $legendColor = $this->getLegendColor($data, $tokens);

        $x = match ($data->legendPosition) {
            'right' => $width - 120.0,
            default => $spec->padding[3],
        };

        $y = match ($data->legendPosition) {
            'top' => $height - 16.0 * max(1, count($data->series)) - 4.0,
            'bottom' => $height - 16.0 * max(1, count($data->series)) - 4.0,
            default => 24.0,
        };

        foreach ($data->series as $i => $series) {
            $color = $series->color ?? $this->getPaletteColor($spec, $data, $tokens, $i);

            // Legend indicator
            $commands[] = new FillRoundedRect($x, $y, 12.0, 12.0, 2.0, Color::rgb($color));

            // Legend label
            $str = new AttributedString();
            $str->append($series->label, Attribute::fromColor($legendColor), Attribute::size(11.0));
            $layout = new TextLayout($str, $font, 100.0, DrawTextAlign::Left);
            [$tw, $th] = $layout->extents();
            $commands[] = new DrawText($layout, $x + 18.0, $y + 1.0);

            $y += 16.0;
        }

        return $commands;
    }

    /** Create a DrawText command for a tick label */
    private function drawTextCommand(string $text, FontDescriptor $font, float $x, float $y, Color $color, int|DrawTextAlign $align = DrawTextAlign::Left): DrawText
    {
        $str = new AttributedString();
        $str->append($text, Attribute::fromColor($color), Attribute::size($font->size()));
        $layout = new TextLayout($str, $font, 100.0, $align);

        return new DrawText($layout, $x, $y);
    }

    private function fontFromTokens(DesignTokens $tokens, float $size): FontDescriptor
    {
        return new FontDescriptor('Inter, -apple-system, sans-serif', $size, \Libui\Generated\Enum\TextWeight::Medium);
    }

    private function getBackgroundColor(ChartData $data, DesignTokens $tokens): Color
    {
        $bg = $data->themeColors()[$data->theme === 'dark' ? 'background' : 'background'];
        return Color::rgb((int) ($bg ?? 0xF8FAFC));
    }

    private function getGridColor(ChartData $data, DesignTokens $tokens): Color
    {
        $grid = $data->themeColors()[$data->theme === 'dark' ? 'grid' : 'grid'];
        return Color::rgb((int) ($grid ?? 0xE2E8F0));
    }

    private function getAxisColor(ChartData $data, DesignTokens $tokens): Color
    {
        $axis = $data->themeColors()[$data->theme === 'dark' ? 'axis' : 'axis'];
        return Color::rgb((int) ($axis ?? 0x64748B));
    }

    private function getTextColor(ChartData $data, DesignTokens $tokens): Color
    {
        $text = $data->themeColors()[$data->theme === 'dark' ? 'text' : 'text'];
        return Color::rgb((int) ($text ?? 0x1E293B));
    }

    private function getLegendColor(ChartData $data, DesignTokens $tokens): Color
    {
        $legend = $data->themeColors()[$data->theme === 'dark' ? 'legend' : 'legend'];
        return Color::rgb((int) ($legend ?? 0x475569));
    }

    private function getPaletteColor(ChartSpec $spec, ChartData $data, DesignTokens $tokens, int $index = 0): int
    {
        $palette = $data->palette ?? $spec->palette;
        if ($palette === null) {
            return 0x3B82F6;
        }

        return $palette[$index % count($palette)] ?? 0x3B82F6;
    }

    private function alphaColor(Color $color, float $alpha): Color
    {
        return Color::rgba($color->r, $color->g, $color->b, $alpha);
    }
}
