<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * A self-drawn selectable list, wired into a {@see Surface}.
 *
 * Builds a vertical column of {@see ListRowSpec} leaves (role List) and manages
 * a single active selection. Rows hover + click through the Surface's normal
 * node-based event routing (no bespoke hit-testing), and the control just swaps
 * each row's `selected` flag on click and repaints.
 *
 * ```php
 * $list = new ListControl('fruits', [
 *     ['id' => 'a', 'label' => 'Apple',  'subtitle' => 'Red'],
 *     ['id' => 'b', 'label' => 'Banana', 'subtitle' => 'Yellow'],
 * ], selected: 0);
 * $tree->child($list->root());
 * $list->bind($surface)->onSelect(fn ($i, $label) => $status->setText($label));
 * ```
 */
final class ListControl
{
    private LayoutNode $root;

    /** @var list<LayoutNode> */
    private array $rows = [];

    private int $selected;

    private ?Surface $surface = null;

    /** @var callable(int,string):void|null */
    private $onSelect = null;

    /**
     * @param list<array{id:string,label:string,subtitle?:string}> $items
     */
    public function __construct(
        private readonly string $name,
        array $items,
        int $selected = 0,
        float $rowHeight = 44.0,
    ) {
        $this->selected = $selected;
        $gap = 2.0;
        $padding = 6.0;
        $totalHeight = count($items) * $rowHeight + max(0, count($items) - 1) * $gap + 2 * $padding;
        $this->root = LayoutNode::column(gap: $gap, padding: $padding, id: $this->name, height: $totalHeight)->withRole(WidgetRole::List);

        foreach ($items as $i => $item) {
            $leaf = LayoutNode::leaf(
                "{$this->name}:row:{$i}",
                new ListRowSpec(
                    label: $item['label'],
                    subtitle: $item['subtitle'] ?? '',
                    selected: $i === $selected,
                ),
                height: $rowHeight,
            );
            $this->rows[] = $leaf;
            $this->root->child($leaf);
        }
    }

    /** The list's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    public function selectedIndex(): int
    {
        return $this->selected;
    }

    /** Register the row click handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        foreach ($this->rows as $i => $row) {
            $surface->onClick($row->id, fn () => $this->select($i));
        }

        return $this;
    }

    /** Change the selected row and repaint. */
    public function select(int $index): void
    {
        if (! isset($this->rows[$index]) || $index === $this->selected) {
            return;
        }

        $this->selected = $index;
        foreach ($this->rows as $i => $row) {
            $spec = $row->spec;
            if (! $spec instanceof ListRowSpec) {
                continue;
            }
            $row->spec = new ListRowSpec(
                label: $spec->label,
                subtitle: $spec->subtitle,
                selected: $i === $index,
                enabled: $spec->enabled,
                hovered: $spec->hovered,
                radius: $spec->radius,
            );
        }

        if ($this->onSelect !== null) {
            $spec = $this->rows[$index]->spec;
            ($this->onSelect)($index, $spec instanceof ListRowSpec ? $spec->label : (string) $index);
        }

        $this->surface?->redraw();
    }

    /** @param callable(int,string):void $fn */
    public function onSelect(callable $fn): static
    {
        $this->onSelect = $fn;

        return $this;
    }
}
