<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\BreadcrumbItemSpec;

/**
 * A self-drawn breadcrumb trail, wired into a {@see Surface}.
 *
 * Builds a row of {@see BreadcrumbItemSpec} leaves (the final one is the active
 * "current" crumb, drawn in primary) plus trailing separators. Each crumb is
 * independently clickable through the Surface's normal node routing.
 *
 * ```php
 * $bc = new BreadcrumbControl('path', [
 *     ['label' => 'Home'], ['label' => 'Library'], ['label' => 'Report'],
 * ]);
 * $tree->child($bc->root());
 * $bc->bind($surface)->onNavigate(fn ($i) => openCrumb($i));
 * ```
 */
final class BreadcrumbControl
{
    private LayoutNode $root;

    /** @var list<LayoutNode> */
    private array $crumbs = [];

    private ?Surface $surface = null;

    /** @var callable(int):void|null */
    private $onNavigate = null;

    /**
     * @param list<array{label:string}> $items
     */
    public function __construct(
        private readonly string $name,
        array $items,
        float $height = 26.0,
        string $separator = '/',
    ) {
        $this->root = LayoutNode::row(gap: 0, align: LayoutStyle::ALIGN_CENTER, id: $this->name);

        foreach ($items as $i => $item) {
            $label = $item['label'];
            $isLast = $i === count($items) - 1;
            $leaf = LayoutNode::leaf(
                "{$this->name}:crumb:{$i}",
                new BreadcrumbItemSpec(
                    label: $label,
                    active: $isLast,
                    isLast: $isLast,
                    separator: $separator,
                ),
                width: max(40.0, mb_strlen($label) * 8.0 + 28.0),
                height: $height,
            );
            $this->crumbs[] = $leaf;
            $this->root->child($leaf);
        }
    }

    /** The breadcrumb's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    /** Register crumb click handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        foreach ($this->crumbs as $i => $crumb) {
            $surface->onClick($crumb->id, fn () => $this->navigate($i));
        }

        return $this;
    }

    /** Fire the navigate callback for the clicked crumb. */
    public function navigate(int $index): void
    {
        if ($this->onNavigate !== null) {
            ($this->onNavigate)($index);
        }
        $this->surface?->redraw();
    }

    /** @param callable(int):void $fn */
    public function onNavigate(callable $fn): static
    {
        $this->onNavigate = $fn;

        return $this;
    }
}
