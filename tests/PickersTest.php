<?php

declare(strict_types=1);

use Yangweijie\Ui2\Pickers\ColorPickerDialog;
use Yangweijie\Ui2\Pickers\DatePickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Pickers\TimePickerDialog;

/**
 * Smoke tests for picker dialogs.
 *
 * These are modal dialogs that require user interaction (clicking OK/Cancel).
 * Without a running event loop and user input, the pick() method will either:
 * - Return null (if the window is destroyed before user interacts)
 * - Block indefinitely (if the event loop runs waiting for input)
 *
 * We test that the classes exist and have the expected static methods.
 * Full integration tests require a running uiMain() loop.
 */

test('ColorPickerDialog has static pick method', function (): void {
    expect(method_exists(ColorPickerDialog::class, 'pick'))->toBeTrue();
});

test('FontPickerDialog has static pick method', function (): void {
    expect(method_exists(FontPickerDialog::class, 'pick'))->toBeTrue();
});

test('DatePickerDialog has static pick method', function (): void {
    expect(method_exists(DatePickerDialog::class, 'pick'))->toBeTrue();
});

test('TimePickerDialog has static pick method', function (): void {
    expect(method_exists(TimePickerDialog::class, 'pick'))->toBeTrue();
});
