<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawTextAlign;
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Yangweijie\Ui2\Rendering\CommandExecutor;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\StrokeArc;

/**
 * @internal Area delegate driving the circular progress bar's drawing.
 *
 * The draw handler no longer calls DrawContext directly. Instead it compiles
 * the widget's visual state into a RenderCommandList (retained-mode), which a
 * CommandExecutor translates into immediate-mode DrawContext calls. This keeps
 * "what to draw" separate from "how to draw it" and lets the command list be
 * inspected, cached, or (later) recorded/replayed.
 */
final class CircleProgressDelegate extends AreaDelegate
{
    public int $progress;
    /** Explicit progress-arc override; null means "use the theme token". */
    public ?Color $color = null;
    public float $thickness = 12.0;
    public DesignTokens $tokens;
    private int $ringSize;

    public function __construct(int $initialProgress, int $ringSize = 200, ?DesignTokens $tokens = null)
    {
        $this->progress = max(0, min(100, $initialProgress));
        $this->tokens = $tokens ?? new DesignTokens();
        $this->ringSize = $ringSize;
    }

    /**
     * The colour used for the progress arc: an explicit setColor() override
     * wins, otherwise it is resolved from the theme's color.primary token.
     */
    public function progressColor(): Color
    {
        return $this->color ?? $this->tokens->color('color.primary');
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $list = $this->buildCommands($params->areaWidth, $params->areaHeight);
        (new CommandExecutor())->execute($ctx, $list);
        $list->free();
    }

    /**
     * Compute the ring geometry for a viewport of ($w × $h).
     *
     * Returns [cx, cy, radius, diameter] or null when the radius collapses to
     * zero (ring too small to draw). Shared by arcCommands() and buildCommands()
     * so the arcs and the centred text always agree on geometry.
     *
     * @return array{cx: float, cy: float, radius: float, diameter: float}|null
     */
    private function geometry(float $w, float $h): ?array
    {
        // When viewport is 0×0 (after tab switch), use content size as fallback.
        // When viewport is correct, center the ring in it.
        if ($w < $this->ringSize || $h < $this->ringSize) {
            $w = $this->ringSize;
            $h = $this->ringSize;
        }

        $cx = $w / 2;
        $cy = $h / 2;

        $minDiameter = $this->thickness * 2 + 8;
        $diameter = max($minDiameter, $this->ringSize - 8);
        $radius = $diameter / 2 - $this->thickness / 2;

        if ($radius <= 0) {
            return null;
        }

        return [$cx, $cy, $radius, $diameter];
    }

    /**
     * Pure: build the ring/track arc commands for a viewport of ($w × $h).
     *
     * Contains no DrawContext or TextLayout work, so it is safe to call
     * headlessly (used by tests). The centred percentage label is added by
     * buildCommands(), which layers a DrawText on top of these arcs.
     *
     * @return list<RenderCommand>
     */
    public function arcCommands(float $w, float $h): array
    {
        $geo = $this->geometry($w, $h);
        if ($geo === null) {
            return [];
        }
        [$cx, $cy, $radius, $diameter] = $geo;

        $trackStroke = new StrokeParams(
            thickness: $this->thickness,
            cap: \Libui\Generated\Enum\DrawLineCap::Round,
            join: \Libui\Generated\Enum\DrawLineJoin::Round,
        );
        $commands = [
            new StrokeArc($cx, $cy, $radius, 0.0, 2 * M_PI, $this->tokens->color('color.track'), $trackStroke),
        ];

        $sweep = ($this->progress / 100.0) * 2 * M_PI;
        if ($sweep > 0) {
            $progressStroke = new StrokeParams(
                thickness: $this->thickness,
                cap: \Libui\Generated\Enum\DrawLineCap::Round,
                join: \Libui\Generated\Enum\DrawLineJoin::Round,
            );
            $commands[] = new StrokeArc($cx, $cy, $radius, -M_PI / 2, $sweep, $this->progressColor(), $progressStroke);
        }

        return $commands;
    }

    /**
     * Build the full command list for one frame: the ring arcs plus the
     * centred percentage label. This is the retained-mode description that
     * CommandExecutor turns into immediate-mode DrawContext calls.
     */
    public function buildCommands(float $w, float $h): RenderCommandList
    {
        $geo = $this->geometry($w, $h);
        if ($geo === null) {
            return new RenderCommandList([]);
        }
        [$cx, $cy, $radius, $diameter] = $geo;

        $commands = $this->arcCommands($w, $h);

        $text = $this->progress . '%';
        $innerDiameter = $diameter - $this->thickness;
        $fontSize = max(14.0, $innerDiameter * 0.10);

        $font = new FontDescriptor('Arial', $fontSize);
        $str = new AttributedString();
        $str->append($text, Attribute::fromColor($this->tokens->color('color.onSurface')), Attribute::size($fontSize));

        $layout = new TextLayout($str, $font, $innerDiameter * 2, DrawTextAlign::Left);
        [$textW, $textH] = $layout->extents();
        $commands[] = new DrawText($layout, $cx - $textW / 2, $cy - $textH / 2);

        return new RenderCommandList($commands);
    }
}
