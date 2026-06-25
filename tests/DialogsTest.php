<?php

declare(strict_types=1);

use Libui\Dialogs;
use Libui\Window;

/**
 * Test the private Dialogs::nullIfEmpty() method via reflection.
 *
 * Constructs a Dialogs instance with a Window created without running
 * the FFI constructor, so this test never touches native libui.
 * The nullIfEmpty method only reads its $value parameter, not $this->parent.
 */

test('nullIfEmpty returns null for empty string', function (): void {
    $dialogs = createTestDialogs();
    $result = invokeNullIfEmpty($dialogs, '');
    expect($result)->toBeNull();
});

test('nullIfEmpty returns input for non-empty value', function (): void {
    $dialogs = createTestDialogs();
    $result = invokeNullIfEmpty($dialogs, '/home/user/file.txt');
    expect($result)->toBe('/home/user/file.txt');
});

test('nullIfEmpty returns input for single character', function (): void {
    $dialogs = createTestDialogs();
    $result = invokeNullIfEmpty($dialogs, '/');
    expect($result)->toBe('/');
});

test('nullIfEmpty preserves whitespace string', function (): void {
    // libui uses strict === '' so whitespace is preserved (documenting current behaviour).
    $dialogs = createTestDialogs();
    $result = invokeNullIfEmpty($dialogs, '   ');
    expect($result)->toBe('   ');
});

function createTestDialogs(): Dialogs
{
    // Create a Window without calling the FFI constructor.
    $window = (new ReflectionClass(Window::class))->newInstanceWithoutConstructor();

    return new Dialogs($window);
}

function invokeNullIfEmpty(Dialogs $dialogs, string $value): ?string
{
    $method = new ReflectionMethod(Dialogs::class, 'nullIfEmpty');
    return $method->invoke($dialogs, $value);
}
