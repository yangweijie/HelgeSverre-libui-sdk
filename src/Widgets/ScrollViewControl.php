<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewSpec;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * A self-drawn scroll viewport (.scroll_view).
 *
 * Wraps arbitrary content in a clipped, scrollable frame: children are laid out
 * by FlexLayout into a content column whose natural height (provided by the
 * caller) typically exceeds the fixed viewport, then the {@see Surface} paints
 * them clipped to the viewport rect and translated by the node's scrollX/scrollY.
 *
 * Scrolling is driven three ways:
 *  - keyboard: Arrow keys scroll the viewport while it holds focus (via Surface::onScroll)
 *  - scrollbar: press/drag the right-edge thumb, or press the track to page
 *  - programmatic: {@see scrollBy()} / {@see scrollTo()}
 *
 * ```php
 * $sv = new ScrollViewControl('log', $rows, width: 320, height: 200, contentHeight: count($rows) * 28);
 * $sv->bind($surface);
 * ```
 */
class ScrollViewControl
{
    private LayoutNode $viewport;

    private LayoutNode $content;

    private ScrollViewSpec $spec;

    private ?Surface $surface = null;

    private bool $thumbDragging = false;

    private float $grabDy = 0.0;

    /** True while the content body (not the scrollbar) is being panned. */
    private bool $bodyDragging = false;

    /** Pointer Y captured at the start of a body pan. */
    private float $grabRy = 0.0;

    /** scrollY captured at the start of a body pan. */
    private float $grabScrollY = 0.0;

    /**
     * @param list<LayoutNode> $children      Content nodes (laid out top-to-bottom).
     * @param float            $width         Viewport width.
     * @param float            $height        Viewport height (clipped window).
     * @param float            $contentHeight Natural full content height (for the thumb + clamp).
     * @param float            $contentWidth  Natural content width (0 = same as $width).
     */
    public function __construct(
        private readonly string $name,
        array $children,
        float $width,
        float $height,
        float $contentHeight,
        float $contentWidth = 0.0,
        private readonly bool $vertical = true,
        private readonly bool $horizontal = false,
        float $radius = 8.0,
        float $gap = 0.0,
        float $padding = 0.0,
    ) {
        $contentWidth = $contentWidth > 0 ? $contentWidth : $width;
        $gutter = $vertical ? ScrollViewRenderer::GUTTER : 0.0;

        $this->content = LayoutNode::column(gap: $gap, padding: $padding, align: LayoutStyle::ALIGN_STRETCH)
            ->withRole(WidgetRole::Group);
        $this->content->style->width = max(0.0, $width - $gutter);
        $this->content->style->height = $contentHeight;
        foreach ($children as $child) {
            $this->content->child($child);
        }

        $this->spec = new ScrollViewSpec(
            scrollX: 0.0,
            scrollY: 0.0,
            contentWidth: $contentWidth,
            contentHeight: $contentHeight,
            viewportWidth: $width,
            viewportHeight: $height,
            radius: $radius,
            vertical: $vertical,
            horizontal: $horizontal,
        );

        $this->viewport = LayoutNode::row(
            id: "scroll:{$name}",
            justify: LayoutStyle::JUSTIFY_START,
            align: LayoutStyle::ALIGN_START,
        )->withRole(WidgetRole::Group);
        $this->viewport->style->width = $width;
        $this->viewport->style->height = $height;
        $this->viewport->spec = $this->spec;
        $this->viewport->child($this->content);
    }

    /** The viewport node to drop into a parent layout. */
    public function root(): LayoutNode
    {
        return $this->viewport;
    }

    /** Register keyboard + scrollbar-drag handlers on a Surface and keep it. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;

        $surface->onScroll("scroll:{$this->name}", fn (float $dx, float $dy) => $this->scrollBy($dx, $dy));
        $surface->onDragEnd("scroll:{$this->name}", function (): void {
            $this->thumbDragging = false;
            $this->bodyDragging = false;
        });
        $surface->onDrag("scroll:{$this->name}", function (float $rx, float $ry, float $w, float $h): void {
            if (getenv('UI2_DEBUG_MOUSE') === '1') {
                fwrite(STDERR, sprintf(
                    "[SCROLL %s] rx=%.1f ry=%.1f w=%.1f h=%.1f scrollY=%s\n",
                    $this->name, $rx, $ry, $w, $h,
                    number_format($this->viewport->scrollY, 1)
                ));
            }
            $gutterX = $w - ScrollViewRenderer::GUTTER;

            // Scrollbar gutter (right edge): thumb drag + track paging.
            if ($rx >= $gutterX) {
                $renderer = new ScrollViewRenderer();
                $thumb = $renderer->verticalThumb($this->currentSpec(), $w, $h);
                if ($thumb === null) {
                    return;
                }

                if (! $this->thumbDragging) {
                    if ($ry >= $thumb[1] && $ry <= $thumb[1] + $thumb[3]) {
                        $this->thumbDragging = true;
                        $this->grabDy = $ry - $thumb[1];
                    } else {
                        // Pressed the track: page toward the click, then grab centre.
                        $page = $h - 2 * ScrollViewRenderer::TRACK_INSET;
                        $this->scrollBy(0, $ry < $thumb[1] ? -$page : $page);
                        $this->thumbDragging = true;
                        $this->grabDy = $thumb[3] / 2;
                    }
                }

                if ($this->thumbDragging) {
                    $centerY = $ry - $this->grabDy + $thumb[3] / 2;
                    $this->setScrollY($renderer->scrollYForThumbCenter($this->currentSpec(), $centerY, $h));
                }

                return;
            }

            // Content body: drag-to-pan. Grab the content under the pointer and
            // translate it with the pointer so it feels like a touch scroll.
            // Guarded by the enabled axis so a horizontal-only view never pans
            // vertically (and vice-versa).
            if (! $this->bodyDragging) {
                $this->bodyDragging = true;
                $this->grabRy = $ry;
                $this->grabScrollY = $this->viewport->scrollY;
            }
            if ($this->spec->vertical) {
                $this->setScrollY($this->grabScrollY + ($this->grabRy - $ry));
            }
        });

        return $this;
    }

    /** Scroll by a delta (px), clamped to the content bounds; repaints. */
    public function scrollBy(float $dx, float $dy): void
    {
        $this->setScrollY($this->viewport->scrollY + $dy);
        if ($this->horizontal) {
            $this->setScrollX($this->viewport->scrollX + $dx);
        }
    }

    public function scrollTo(float $y): void
    {
        $this->setScrollY($y);
    }

    public function scrollY(): float
    {
        return $this->viewport->scrollY;
    }

    public function scrollX(): float
    {
        return $this->viewport->scrollX;
    }

    public function viewportHeight(): float
    {
        return $this->spec->viewportHeight;
    }

    public function contentHeight(): float
    {
        return $this->spec->contentHeight;
    }

    /** A live spec snapshot carrying the current scroll offset (for thumb math). */
    private function currentSpec(): ScrollViewSpec
    {
        return new ScrollViewSpec(
            scrollX: $this->viewport->scrollX,
            scrollY: $this->viewport->scrollY,
            contentWidth: $this->spec->contentWidth,
            contentHeight: $this->spec->contentHeight,
            viewportWidth: $this->spec->viewportWidth,
            viewportHeight: $this->spec->viewportHeight,
            radius: $this->spec->radius,
            vertical: $this->spec->vertical,
            horizontal: $this->spec->horizontal,
        );
    }

    private function setScrollY(float $y): void
    {
        $max = max(0.0, $this->spec->contentHeight - $this->spec->viewportHeight);
        $clamped = max(0.0, min($max, $y));
        if ($clamped === $this->viewport->scrollY) {
            return;
        }
        $this->viewport->scrollY = $clamped;
        // Notify after the offset is updated so the IME overlay can be
        // repositioned against the new scroll position (no one-frame lag).
        $this->surface?->onScrollContainerScrolled($this->viewport->id);
        $this->surface?->redraw();
    }

    private function setScrollX(float $x): void
    {
        $max = max(0.0, $this->spec->contentWidth - $this->spec->viewportWidth);
        $clamped = max(0.0, min($max, $x));
        if ($clamped === $this->viewport->scrollX) {
            return;
        }
        $this->viewport->scrollX = $clamped;
        $this->surface?->onScrollContainerScrolled($this->viewport->id);
        $this->surface?->redraw();
    }
}
