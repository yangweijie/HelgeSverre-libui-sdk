<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\ChartV2;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\Attribute;
use Libui\Text\AttributedString;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Rendering\CommandExecutor;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ChartRenderer as ChartWidgetRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ChartSpec;

/**
 * Interactive chart widget — wraps the static ChartRenderer in an AreaDelegate
 * to handle mouse hover, tooltips, and animated redraws.
 *
 * Usage:
 *   $chart = new ChartWidget($area, $data, $tokens);
 *   $chart->setData([$series]);
 *   $chart->redraw();
 */
final class ChartWidget extends AreaDelegate
{
    private ChartData $data;
    private DesignTokens $tokens;
    private ?Area $area = null;
    private ?array $hover = null; // ['i' => int, 'j' => int] for cartesian, ['slice' => int] for pie
    private array $hoverPx = [0.0, 0.0];
    private ?RenderCommandList $cachedCommands = null;

    /**
     * @param Area         $area   The Area to render into
     * @param ChartData    $data   Chart data model
     * @param DesignTokens $tokens Design tokens for theme
     */
    public function __construct(Area $area, ChartData $data, DesignTokens $tokens)
    {
        $this->area = $area;
        $this->data = $data;
        $this->tokens = $tokens;
        parent::bindArea($area);
    }

    /** Update chart data and redraw */
    public function setData(array $series): self
    {
        $this->data->series = $series;
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Update category labels */
    public function setLabels(array $labels): self
    {
        $this->data->labels = $labels;
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Change chart type */
    public function setType(string $type): self
    {
        $this->data->type = $type;
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Set title */
    public function setTitle(string $title): self
    {
        $this->data->title = $title;
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Apply a theme */
    public function applyTheme(string $theme): self
    {
        $this->data->applyTheme($theme);
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Apply custom palette */
    public function palette(int ...$hex): self
    {
        $this->data->palette = $hex;
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    /** Configure the data model */
    public function configure(callable $fn): self
    {
        $fn($this->data);
        $this->cachedCommands = null;
        $this->redraw();
        return $this;
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $W = (float) $params->areaWidth;
        $H = (float) $params->areaHeight;

        if ($W <= 0 || $H <= 0) {
            return;
        }

        // Build or cache render commands
        if ($this->cachedCommands === null || $this->cachedCommands->width !== $W || $this->cachedCommands->height !== $H) {
            $renderer = new ChartWidgetRenderer();
            $spec = new ChartSpec($this->data);
            $this->cachedCommands = $renderer->render($spec, $this->tokens, $W, $H);
        }

        // Execute commands
        $executor = new CommandExecutor();
        $executor->execute($ctx, $this->cachedCommands);

        // Draw tooltip if hovering
        if ($this->hover !== null) {
            $this->drawTooltip($ctx, $W, $H);
        }
    }

    public function mouse(AreaMouseEvent $e): void
    {
        // Simple hover detection — could be enhanced with hit testing
        $this->hoverPx = [$e->x, $e->y];

        // For now, just track mouse position; tooltip will show if hovering over data
        // This is a simplified implementation — a full implementation would do
        // proper hit testing against the chart's data points
        if ($e->down === 0 && $e->up === 0 && $e->held === 0) {
            $this->hover = ['hovered' => true, 'x' => $e->x, 'y' => $e->y];
            $this->redraw();
        }
    }

    public function key(AreaKeyEvent $e): bool
    {
        // Could handle keyboard navigation in future
        return false;
    }

    private function drawTooltip(DrawContext $ctx, float $W, float $H): void
    {
        $this->drawTooltipBackground($ctx);
        $this->drawTooltipText($ctx);
    }

    private function drawTooltipBackground(DrawContext $ctx): void
    {
        $x = $this->hoverPx[0] + 12.0;
        $y = $this->hoverPx[1] + 12.0;

        // Ensure tooltip stays within bounds
        $x = min($x, $W - 200.0);
        $y = min($y, $H - 60.0);

        $x = max($x, 0.0);
        $y = max($y, 0.0);

        $ctx->fillRoundedRect($x, $y, 180.0, 40.0, 6.0, Brush::rgb(0x1E293B));
        $ctx->strokeRect($x, $y, 180.0, 40.0, Brush::rgb(0x334155), \Libui\Draw\StrokeParams::solid(1.0));
    }

    private function drawTooltipText(DrawContext $ctx): void
    {
        $x = $this->hoverPx[0] + 12.0;
        $y = $this->hoverPx[1] + 12.0;

        $x = min($x, $W - 200.0);
        $y = min($y, $H - 60.0);

        $x = max($x, 0.0);
        $y = max($y, 0.0);

        $font = new FontDescriptor($this->data->fontFamily, 12.0, \Libui\Generated\Enum\TextWeight::Medium);
        $text = 'Hover tooltip';

        $str = new AttributedString();
        $str->append($text, Attribute::fromColor(Color::rgb(0xF8FAFC)), Attribute::size(12.0));
        $layout = new TextLayout($str, $font, 160.0, DrawTextAlign::Left);

        $ctx->text($layout, $x + 10.0, $y + 12.0);
    }

    public function redraw(): void
    {
        $this->cachedCommands = null;
        $this->area?->queueRedrawAll();
    }
}
