<?php

declare(strict_types=1);

use Libui\Control;
use Yangweijie\Ui2\Widgets\TableView;

// ---------------------------------------------------------------------------
// Construction
// ---------------------------------------------------------------------------

test('TableView can be constructed with columns and rows', function (): void {
    $table = new TableView(
        columns: ['Name', 'Age'],
        rows: [
            ['Alice', 30],
            ['Bob', 25],
        ],
    );
    expect($table->root())->toBeInstanceOf(Control::class);
    expect($table->rowCount())->toBe(2);
});

test('TableView can be constructed with empty rows', function (): void {
    $table = new TableView(columns: ['Col A', 'Col B']);
    expect($table->rowCount())->toBe(0);
});

test('TableView with checkbox columns', function (): void {
    $table = new TableView(
        columns: ['Name', 'Active'],
        rows: [['Alice', true]],
        checkbox: [1],
    );
    expect($table->rowCount())->toBe(1);
});

test('TableView with editable columns', function (): void {
    $table = new TableView(
        columns: ['Name', 'Age'],
        rows: [['Alice', 30]],
        editable: [0, 1],
    );
    expect($table->rowCount())->toBe(1);
});

// ---------------------------------------------------------------------------
// setRows
// ---------------------------------------------------------------------------

test('setRows replaces all rows', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice'], ['Bob']],
    );
    expect($table->rowCount())->toBe(2);

    $table->setRows([['Charlie'], ['Diana'], ['Eve']]);
    expect($table->rowCount())->toBe(3);
});

test('setRows to empty clears all rows', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice']],
    );
    $table->setRows([]);
    expect($table->rowCount())->toBe(0);
});

test('setRows returns static for chaining', function (): void {
    $table = new TableView(columns: ['Name']);
    $result = $table->setRows([['Alice']]);
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// addRow
// ---------------------------------------------------------------------------

test('addRow appends a row', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice']],
    );
    $table->addRow(['Bob']);
    expect($table->rowCount())->toBe(2);
});

test('addRow returns static for chaining', function (): void {
    $table = new TableView(columns: ['Name']);
    $result = $table->addRow(['Alice']);
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// updateRow
// ---------------------------------------------------------------------------

test('updateRow changes a specific row', function (): void {
    $table = new TableView(
        columns: ['Name', 'Age'],
        rows: [['Alice', 30]],
    );
    $table->updateRow(0, ['Alicia', 31]);
    expect($table->rowCount())->toBe(1);
});

test('updateRow returns static for chaining', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice']],
    );
    $result = $table->updateRow(0, ['Bob']);
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// removeRow
// ---------------------------------------------------------------------------

test('removeRow removes a specific row', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice'], ['Bob'], ['Charlie']],
    );
    $table->removeRow(1);
    expect($table->rowCount())->toBe(2);
});

test('removeRow returns static for chaining', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice']],
    );
    $result = $table->removeRow(0);
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// setCellValue
// ---------------------------------------------------------------------------

test('setCellValue updates a single cell', function (): void {
    $table = new TableView(
        columns: ['Name', 'Age'],
        rows: [['Alice', 30]],
    );
    $table->setCellValue(0, 1, 31);
    expect($table->rowCount())->toBe(1);
});

test('setCellValue returns static for chaining', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice']],
    );
    $result = $table->setCellValue(0, 0, 'Bob');
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// sortByColumn
// ---------------------------------------------------------------------------

test('sortByColumn returns static for chaining', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Charlie'], ['Alice'], ['Bob']],
    );
    $result = $table->sortByColumn(0, 'asc');
    expect($result)->toBe($table);
});

test('sortByColumn with desc direction', function (): void {
    $table = new TableView(
        columns: ['Name'],
        rows: [['Alice'], ['Bob']],
    );
    $result = $table->sortByColumn(0, 'desc');
    expect($result)->toBe($table);
});

// ---------------------------------------------------------------------------
// table() / model() accessors
// ---------------------------------------------------------------------------

test('table returns the underlying Table', function (): void {
    $table = new TableView(columns: ['Name']);
    expect($table->table())->toBeInstanceOf(\Libui\Table::class);
});

test('model returns the underlying TableModel', function (): void {
    $table = new TableView(columns: ['Name']);
    expect($table->model())->toBeInstanceOf(\Libui\TableModel::class);
});
