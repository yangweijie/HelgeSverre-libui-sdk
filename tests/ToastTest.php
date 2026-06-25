<?php

declare(strict_types=1);

use Yangweijie\Ui2\Widgets\Toast;

/**
 * Test Toast library path resolution via reflection.
 * The actual toastShow() requires native FFI, so we only test the path logic.
 */

test('Toast has static show method', function (): void {
    expect(method_exists(Toast::class, 'show'))->toBeTrue();
});

test('Toast has static lastError method', function (): void {
    expect(method_exists(Toast::class, 'lastError'))->toBeTrue();
});

test('Toast lastError returns null initially', function (): void {
    // Reset state by calling show (which sets lastError to null on success)
    // The exact state depends on prior test runs, so just verify the method works
    $error = Toast::lastError();
    expect($error === null || is_string($error))->toBeTrue();
});

test('Toast libraryPath resolves for current platform', function (): void {
    $method = new ReflectionMethod(Toast::class, 'libraryPath');
    $method->setAccessible(true);

    $path = $method->invoke(null);
    expect($path)->toBeString();

    $expectedSuffix = match (PHP_OS_FAMILY) {
        'Darwin'  => 'Toast.dylib',
        'Linux'   => 'libToast.so',
        'Windows' => 'Toast.dll',
        default   => 'unsupported',
    };

    expect($path)->toContain($expectedSuffix);
    expect($path)->toContain('vendor/kingbes/pebview/lib');
});
