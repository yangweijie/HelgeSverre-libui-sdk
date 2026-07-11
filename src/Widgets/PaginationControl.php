<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PaginationItemSpec;

/**
 * A self-drawn windowed pagination strip, wired into a {@see Surface}.
 *
 * Builds a row (role TabList-like) of {@see PaginationItemSpec} tokens: a prev
 * glyph, a window of page numbers around the active page with "…" gaps, and a
 * next glyph. Clicking a token activates that page through the Surface's normal
 * node routing; prev/next are auto-disabled at the ends.
 *
 * ```php
 * $pager = new PaginationControl('p', totalPages: 20, active: 5);
 * $tree->child($pager->root());
 * $pager->bind($surface)->onChange(fn ($page) => loadPage($page));
 * ```
 */
final class PaginationControl
{
    private LayoutNode $root;

    /** @var list<LayoutNode> */
    private array $tokens = [];

    private int $total;

    private int $active;

    private ?Surface $surface = null;

    /** @var callable(int):void|null */
    private $onChange = null;

    /**
     * @param int $maxButtons Maximum clickable page tokens shown before gaps kick in.
     */
    public function __construct(
        private readonly string $name,
        int $totalPages,
        int $active = 1,
        private float $size = 32.0,
        private int $maxButtons = 7,
    ) {
        $this->total = max(1, $totalPages);
        $this->active = $this->clamp($active);
        $this->root = LayoutNode::row(gap: 4, align: LayoutStyle::ALIGN_CENTER, id: $this->name);
        $this->rebuild();
    }

    /** The pager's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    public function activePage(): int
    {
        return $this->active;
    }

    private function clamp(int $page): int
    {
        return max(1, min($this->total, $page));
    }

    /** Compute the visible token window. */
    private function window(): array
    {
        $items = [];
        $items[] = ['label' => '‹', 'kind' => 'prev', 'page' => $this->active - 1, 'enabled' => $this->active > 1];

        if ($this->total <= $this->maxButtons) {
            for ($p = 1; $p <= $this->total; $p++) {
                $items[] = ['label' => (string) $p, 'kind' => 'page', 'page' => $p, 'enabled' => true];
            }
        } else {
            $items[] = ['label' => '1', 'kind' => 'page', 'page' => 1, 'enabled' => true];
            $start = max(2, $this->active - 1);
            $end = min($this->total - 1, $this->active + 1);
            if ($start > 2) {
                $items[] = ['label' => '…', 'kind' => 'gap', 'page' => null, 'enabled' => false];
            }
            for ($p = $start; $p <= $end; $p++) {
                $items[] = ['label' => (string) $p, 'kind' => 'page', 'page' => $p, 'enabled' => true];
            }
            if ($end < $this->total - 1) {
                $items[] = ['label' => '…', 'kind' => 'gap', 'page' => null, 'enabled' => false];
            }
            $items[] = ['label' => (string) $this->total, 'kind' => 'page', 'page' => $this->total, 'enabled' => true];
        }

        $items[] = ['label' => '›', 'kind' => 'next', 'page' => $this->active + 1, 'enabled' => $this->active < $this->total];

        return $items;
    }

    private function rebuild(): void
    {
        $this->root->children = [];
        $this->tokens = [];
        foreach ($this->window() as $i => $item) {
            $leaf = LayoutNode::leaf(
                "{$this->name}:tok:{$i}",
                new PaginationItemSpec(
                    label: $item['label'],
                    active: $item['kind'] === 'page' && $item['page'] === $this->active,
                    kind: $item['kind'],
                    enabled: $item['enabled'],
                ),
                width: $this->size,
                height: $this->size,
            );
            $this->tokens[] = $leaf;
            $this->root->child($leaf);
        }
    }

    /** Register token click handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        foreach ($this->tokens as $i => $tok) {
            $spec = $tok->spec;
            if (! $spec instanceof PaginationItemSpec || $spec->kind === 'gap') {
                continue;
            }
            // Page number isn't stored on the spec (only label/kind); derive it.
            $page = match ($spec->kind) {
                'prev' => $this->active - 1,
                'next' => $this->active + 1,
                default => (int) $spec->label, // 'page' token: label is the number
            };
            $surface->onClick($tok->id, fn () => $this->goto($page));
        }

        return $this;
    }

    /** Activate a page, rebuild the window, and repaint. */
    public function goto(int $page): void
    {
        $page = $this->clamp($page);
        if ($page === $this->active) {
            return;
        }
        $this->active = $page;
        $this->rebuild();
        $this->surface?->refreshFocusables();
        if ($this->onChange !== null) {
            ($this->onChange)($page);
        }
        $this->surface?->redraw();
    }

    /** @param callable(int):void $fn */
    public function onChange(callable $fn): static
    {
        $this->onChange = $fn;

        return $this;
    }
}
