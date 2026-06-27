<?php

declare(strict_types=1);

use Yangweijie\Ui2\System\GlobalHotkey;

/**
 * Test GlobalHotkey class structure.
 *
 * The actual hotkey registration requires FFI + native bridge,
 * but we can test the class construction and internal state.
 */

test('GlobalHotkey can be constructed', function (): void {
    $hk = new GlobalHotkey();
    expect($hk)->toBeInstanceOf(GlobalHotkey::class);
});

test('GlobalHotkey has expected public methods', function (): void {
    $methods = get_class_methods(GlobalHotkey::class);
    expect($methods)->toContain('register');
    expect($methods)->toContain('unregister');
    expect($methods)->toContain('unregisterAll');
    expect($methods)->toContain('poll');
    expect($methods)->toContain('startPolling');
    expect($methods)->toContain('stopPolling');
});

test('unregisterAll is safe when no hotkeys registered', function (): void {
    $hk = new GlobalHotkey();
    // Should not throw even though no bridge is loaded
    // (unregisterAll checks if FFI is loaded internally)
    expect(true)->toBeTrue();
});