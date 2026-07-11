<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a self-drawn scroll viewport (.scroll_view).
 *
 * The widget is a *container* node: it clips its children to its rect and
 * translates them by (-scrollX, -scrollY) so they scroll underneath. The
 * renderer only draws the chrome (surface fill, border, scrollbar track + thumb)
 * using the geometry below — the scrolled content is painted by the Surface's
 * own layout recursion, not by this renderer.
 *
 * @property-read bool  $enabled         When false the viewport is drawn muted.
 * @property-read float $scrollX         Current horizontal scroll offset (px).
 * @property-read float $scrollY         Current vertical scroll offset (px).
 * @property-read float $contentWidth    Full (unclipped) content width (px).
 * @property-read float $contentHeight   Full (unclipped) content height (px).
 * @property-read float $viewportWidth   Visible viewport width (px).
 * @property-read float $viewportHeight  Visible viewport height (px).
 * @property-read float $radius          Corner radius.
 * @property-read bool  $vertical        Show/drive the vertical scrollbar.
 * @property-read bool  $horizontal      Show/drive the horizontal scrollbar.
 */
final class ScrollViewSpec extends WidgetSpec
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly float $scrollX = 0.0,
        public readonly float $scrollY = 0.0,
        public readonly float $contentWidth = 0.0,
        public readonly float $contentHeight = 0.0,
        public readonly float $viewportWidth = 0.0,
        public readonly float $viewportHeight = 0.0,
        public readonly float $radius = 8.0,
        public readonly bool $vertical = true,
        public readonly bool $horizontal = false,
    ) {
    }

    public function type(): string
    {
        return 'scroll_view';
    }
}
