<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * A widget spec that embeds a user-supplied draw callback into a
 * Surface's LayoutNode tree.
 *
 * Place this as a leaf node in a LayoutNode tree. The CanvasRenderer
 * will produce a DrawCallback command that invokes your closure with
 * the DrawContext and allocated width/height.
 *
 * Usage:
 * ```php
 * $canvas = new CanvasSpec(function (DrawContext $ctx, float $w, float $h): void {
 *     $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0xFF0000));
 * });
 *
 * $layout = LayoutNode::column()
 *     ->child(LayoutNode::leaf('header', new LabelSpec('Title'), height: 30.0))
 *     ->child(LayoutNode::leaf('chart', $canvas, height: 200.0));
 *
 * $surface = new Surface($layout);
 * ```
 */
final class CanvasSpec extends WidgetSpec
{
    /**
     * @param \Closure(\Libui\Draw\DrawContext, float, float): void $callback
     *   Called with (DrawContext, allocatedWidth, allocatedHeight).
     * @param float|null $background   Background hex color, or null for transparent.
     */
    public function __construct(
        public readonly \Closure $callback,
        public readonly ?float $background = null,
    ) {
    }

    public function type(): string
    {
        return 'canvas';
    }
}
