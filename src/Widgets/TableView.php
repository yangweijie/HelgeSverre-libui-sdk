<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Control;
use Libui\Table;
use Libui\TableModel;
use Libui\TableModelDelegate;
use Libui\Generated\Enum\SortIndicator;
use Libui\Generated\Enum\TableSelectionMode;
use Libui\Generated\Enum\TableValueType;
use Yangweijie\Ui2\Composite;

/**
 * A simplified table widget built on the upstream {@see Table}.
 *
 * TableView provides a fluent API for common table patterns without dealing
 * with the upstream {@see TableModelDelegate} directly. It accepts arrays of
 * data and auto-configures columns from the column definitions.
 *
 * Editable and checkbox columns can be specified so that users can modify
 * values in-place. Sorting is available via {@see sortByColumn()} or by
 * listening to header clicks.
 *
 * ```php
 * $table = new TableView(
 *     columns: ['Name', 'Age', 'Active'],
 *     rows: [
 *         ['Alice', 30, true],
 *         ['Bob', 25, false],
 *     ],
 *     editable: [0, 1],     // Name + Age are editable
 *     checkbox: [2],        // Active is a checkbox
 * );
 * ```
 *
 * For dynamic data, use {@see setRows()}, {@see addRow()}, {@see updateRow()},
 * and {@see removeRow()} — changes are pushed to the model automatically.
 */
class TableView extends Composite
{
    /** @var list<string> */
    private array $columns;

    /** @var list<int> columns that support in-place text editing */
    private array $editable;

    /** @var list<int> columns rendered as checkboxes */
    private array $checkbox;

    private TableViewDelegate $delegate;

    private Table $table;

    /**
     * @param list<string>                $columns  Column headers.
     * @param list<list<string|int|bool>> $rows     Row data (each row is a positional cell list).
     * @param list<int>                   $editable Column indices whose cells are text-editable in-place.
     * @param list<int>                   $checkbox Column indices rendered as checkboxes (cells hold bool values).
     */
    public function __construct(array $columns, array $rows = [], array $editable = [], array $checkbox = [])
    {
        $this->columns = array_values($columns);
        $this->editable = $editable;
        $this->checkbox = $checkbox;
        $this->delegate = new TableViewDelegate(array_values($rows), $this->columns, $checkbox, $this->editable);
        $this->table = Table::fromDelegate($this->delegate);

        foreach ($this->columns as $i => $name) {
            if (in_array($i, $checkbox, true)) {
                $editableCol = in_array($i, $editable, true) ? $i : null;
                $this->table->appendCheckboxColumn((string) $name, $i, $editableCol ?? $i);
            } else {
                $editableCol = in_array($i, $editable, true) ? $i : null;
                $this->table->appendTextColumn((string) $name, $i, $editableCol);
            }
        }
    }

    public function root(): Control
    {
        return $this->table;
    }

    /**
     * The underlying upstream Table for advanced operations.
     */
    public function table(): Table
    {
        return $this->table;
    }

    /**
     * The underlying TableModel for row-level notifications.
     */
    public function model(): TableModel
    {
        return $this->table->model();
    }

    /**
     * Replace all rows and notify the model.
     *
     * @param list<list<string|int|bool>> $rows
     */
    public function setRows(array $rows): static
    {
        $oldCount = $this->delegate->numRows();
        $rows = array_values($rows);
        $newCount = count($rows);
        $this->delegate->rows = $rows;

        $model = $this->table->model();

        if ($newCount > $oldCount) {
            for ($i = $oldCount; $i < $newCount; $i++) {
                $model->rowInserted($i);
            }
        } elseif ($newCount < $oldCount) {
            for ($i = $oldCount - 1; $i >= $newCount; $i--) {
                $model->rowDeleted($i);
            }
        }

        $changed = min($oldCount, $newCount);
        for ($i = 0; $i < $changed; $i++) {
            $model->rowChanged($i);
        }

        return $this;
    }

    /**
     * Add a single row at the end.
     *
     * @param list<string|int|bool> $row
     */
    public function addRow(array $row): static
    {
        $this->delegate->rows[] = array_values($row);
        $this->table->model()->rowInserted(count($this->delegate->rows) - 1);
        return $this;
    }

    /**
     * Update a specific row.
     *
     * @param int                   $index
     * @param list<string|int|bool> $row
     */
    public function updateRow(int $index, array $row): static
    {
        $this->delegate->rows[$index] = array_values($row);
        $this->table->model()->rowChanged($index);
        return $this;
    }

    /**
     * Remove a row by index.
     */
    public function removeRow(int $index): static
    {
        array_splice($this->delegate->rows, $index, 1);
        $this->table->model()->rowDeleted($index);
        return $this;
    }

    /**
     * Current row count.
     */
    public function rowCount(): int
    {
        return $this->delegate->numRows();
    }

    /**
     * Update a single cell value in-place and notify the model.
     */
    public function setCellValue(int $row, int $column, string|int|bool $value): static
    {
        $this->delegate->rows[$row][$column] = $value;
        $this->table->model()->rowChanged($row);
        return $this;
    }

    /**
     * Sort rows by a column using a comparator. Resets sort indicators
     * on all columns first, then sets the clicked column's indicator.
     *
     * @param int    $column    Column index.
     * @param string $direction 'asc' (default) or 'desc'.
     */
    public function sortByColumn(int $column, string $direction = 'asc'): static
    {
        $colCount = count($this->columns);

        for ($i = 0; $i < $colCount; $i++) {
            $this->table->setSortIndicator($i, SortIndicator::None);
        }
        $this->table->setSortIndicator(
            $column,
            $direction === 'desc' ? SortIndicator::Descending : SortIndicator::Ascending,
        );

        $rows = $this->delegate->rows;
        usort($rows, function (array $a, array $b) use ($column, $direction): int {
            $cmp = $a[$column] <=> $b[$column];
            return $direction === 'desc' ? -$cmp : $cmp;
        });
        $this->delegate->rows = $rows;

        $model = $this->table->model();
        $n = count($rows);
        for ($i = 0; $i < $n; $i++) {
            $model->rowChanged($i);
        }

        return $this;
    }

    // ========================================================================
    // Event handlers
    // ========================================================================

    /**
     * Register a callback for when a row is clicked.
     *
     * Signature: `fn (Table $t, int $row): void`
     */
    public function onRowClicked(callable $cb): static
    {
        $this->table->onRowClicked($cb);
        return $this;
    }

    /**
     * Register a callback for when a row is double-clicked.
     *
     * Signature: `fn (Table $t, int $row): void`
     */
    public function onRowDoubleClicked(callable $cb): static
    {
        $this->table->onRowDoubleClicked($cb);
        return $this;
    }

    /**
     * Register a callback for selection changes.
     *
     * Signature: `fn (): void`
     */
    public function onSelectionChanged(callable $cb): static
    {
        $this->table->onSelectionChanged($cb);
        return $this;
    }

    /**
     * Register a callback for when a column header is clicked.
     *
     * Signature: `fn (Table $t, int $column): void`
     * Pair with {@see sortByColumn()} to implement sortable columns.
     */
    public function onHeaderClicked(callable $cb): static
    {
        $this->table->onHeaderClicked($cb);
        return $this;
    }

    // ========================================================================
    // Selection
    // ========================================================================

    /**
     * Get selected row indices.
     *
     * @return list<int>
     */
    public function selectedRows(): array
    {
        return $this->table->selectedRows();
    }

    /**
     * Set the selection mode.
     */
    public function setSelectionMode(TableSelectionMode $mode): static
    {
        $this->table->setSelectionMode($mode);
        return $this;
    }

    // ========================================================================
    // Column display
    // ========================================================================

    /**
     * Show or hide the column headers.
     */
    public function setHeaderVisible(bool $visible): static
    {
        $this->table->setHeaderVisible($visible);
        return $this;
    }

    /**
     * Set a column's sort indicator arrow.
     */
    public function setSortIndicator(int $column, SortIndicator $indicator): static
    {
        $this->table->setSortIndicator($column, $indicator);
        return $this;
    }
}

/**
 * Mutable table model delegate used by {@see TableView}.
 *
 * Holds rows as a public array so TableView can mutate them directly
 * and emit row-level model notifications.
 *
 * @internal
 */
final class TableViewDelegate extends TableModelDelegate
{
    /** @var list<list<string|int|bool>> */
    public array $rows;

    /** @var list<string> */
    private array $columns;

    /** @var list<int> */
    private array $checkboxColumns;

    /** @var list<int> */
    private array $editableColumns;

    /**
     * @param list<list<string|int|bool>> $rows
     * @param list<string>                $columns
     * @param list<int>                   $checkboxColumns
     * @param list<int>                   $editableColumns
     */
    public function __construct(array $rows, array $columns, array $checkboxColumns = [], array $editableColumns = [])
    {
        $this->rows = $rows;
        $this->columns = $columns;
        $this->checkboxColumns = $checkboxColumns;
        $this->editableColumns = $editableColumns;
    }

    public function numColumns(): int
    {
        return count($this->columns);
    }

    public function numRows(): int
    {
        return count($this->rows);
    }

    public function columnType(int $column): TableValueType
    {
        return in_array($column, $this->checkboxColumns, true)
            ? TableValueType::Int
            : TableValueType::String;
    }

    public function cellValue(int $row, int $column): string|int|bool|\Libui\Color|\Libui\Image|null
    {
        return $this->rows[$row][$column] ?? '';
    }

    /**
     * Store a value edited by the user through the UI.
     */
    public function setCellValue(int $row, int $column, mixed $value): void
    {
        if (isset($this->rows[$row])) {
            $this->rows[$row][$column] = $value;
        }
    }

    /**
     * Allow editing on columns marked as editable.
     */
    public function cellEditable(int $row, int $column): ?bool
    {
        return in_array($column, $this->editableColumns, true) ? true : null;
    }
}
