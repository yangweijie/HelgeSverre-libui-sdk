<?php

declare(strict_types=1);

use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;
use Yangweijie\Ui2\Dialogs\MessageBox;

/**
 * Smoke tests for dialog classes.
 *
 * MessageBox, DialogConfirm, and DialogPrompt are modal dialogs that
 * require a parent Window and user interaction. We test that the classes
 * exist and have the expected static methods.
 */

test('MessageBox has static info method', function (): void {
    expect(method_exists(MessageBox::class, 'info'))->toBeTrue();
});

test('MessageBox has static warning method', function (): void {
    expect(method_exists(MessageBox::class, 'warning'))->toBeTrue();
});

test('MessageBox has static error method', function (): void {
    expect(method_exists(MessageBox::class, 'error'))->toBeTrue();
});

test('DialogConfirm has static ask method', function (): void {
    expect(method_exists(DialogConfirm::class, 'ask'))->toBeTrue();
});

test('DialogPrompt has static ask method', function (): void {
    expect(method_exists(DialogPrompt::class, 'ask'))->toBeTrue();
});
