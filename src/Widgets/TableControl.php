<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TableRowSpec;

/**
 * A self-drawn data table, wired into a {@see Surface}.
 *
 * Builds a header {@see TableRowSpec} plus one row leaf per record, all sharing
 * the same column layout (relative widths carried on each row spec). Clicking a
 * data row selects it (token-driven selection highlight); hover/click go through
 * the Surface's normal node-based routing, so no bespoke hit-testing is needed.
 *
 * ```php
 * $table = new TableControl('users',
 *     columns: [['label' => 'Name', 'width' => 2], ['label' => 'Role', 'width' => 1]],
 *     rows:    [['cells' => ['Ada', 'Admin']], ['cells' => ['Linus', 'User']]],
 *     selected: 0,
 * );
 * $tree->child($table->root());
 * $table->bind($surface);
 * ```
 */
final class TableControl
{
    private LayoutNode $root;

    /** @var list<LayoutNode> */
    private array $rows = [];

    /** @var list<float> */
    private array $widths;

    private int $selected;

    private ?Surface $surface = null;

    /** @var callable(int):void|null */
    private $onSelect = null;

    /**
     * @param list<array{label:string,width?:float}> $columns
     * @param list<array{cells:list<string>}>        $rows
     */
    public function __construct(
        private readonly string $name,
        array $columns,
        array $rows,
        int $selected = 0,
        float $rowHeight = 34.0,
        float $headerHeight = 38.0,
    ) {
        $this->selected = $selected;
        $this->widths = array_map(static fn ($c) => (float) ($c['width'] ?? 1.0), $columns);
        $headerCells = array_map(static fn ($c) => $c['label'], $columns);

        $totalHeight = $headerHeight + count($rows) * $rowHeight;
        $this->root = LayoutNode::column(gap: 0, padding: 0, id: $this->name, height: $totalHeight);

        $this->root->child(LayoutNode::leaf(
            "{$this->name}:header",
            new TableRowSpec(cells: $headerCells, widths: $this->widths, header: true),
            height: $headerHeight,
        ));

        foreach ($rows as $i => $row) {
            $leaf = LayoutNode::leaf(
                "{$this->name}:row:{$i}",
                new TableRowSpec(
                    cells: $row['cells'],
                    widths: $this->widths,
                    selected: $i === $selected,
                ),
                height: $rowHeight,
            );
            $this->rows[] = $leaf;
            $this->root->child($leaf);
        }
    }

    /** The table's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    public function selectedIndex(): int
    {
        return $this->selected;
    }

    /** Register row click handlers on a Surface and keep it for repaints. */
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
            if (! $spec instanceof TableRowSpec) {
                continue;
            }
            $row->spec = new TableRowSpec(
                cells: $spec->cells,
                widths: $spec->widths,
                header: $spec->header,
                selected: $i === $index,
                enabled: $spec->enabled,
                hovered: $spec->hovered,
                radius: $spec->radius,
            );
        }

        if ($this->onSelect !== null) {
            ($this->onSelect)($index);
        }

        $this->surface?->redraw();
    }

    /** @param callable(int):void $fn */
    public function onSelect(callable $fn): static
    {
        $this->onSelect = $fn;

        return $this;
    }
}
