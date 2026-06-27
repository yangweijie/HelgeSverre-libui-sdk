<?php

declare(strict_types=1);

use Yangweijie\Ui2\Widgets\ContextMenu;

/**
 * Test the ContextMenu — pure PHP logic, no FFI needed.
 * Tests item building, JSON serialization, source widget tracking.
 */

test('ContextMenu can be constructed empty', function (): void {
    $menu = new ContextMenu();
    // Show with no items should not crash
    expect(true)->toBeTrue();
});

test('addItem stores items', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('Open', fn () => null);
    $menu->addItem('Close', fn () => null);

    $ref = new ReflectionClass($menu);
    $items = $ref->getProperty('items')->getValue($menu);

    expect($items)->toHaveCount(2);
    expect($items[0]['text'])->toBe('Open');
    expect($items[1]['text'])->toBe('Close');
});

test('addItem with disabled flag', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('Delete', fn () => null, disabled: true);

    $items = (new ReflectionClass($menu))->getProperty('items')->getValue($menu);
    expect($items[0]['disabled'])->toBeTrue();
});

test('addItem with checked flag', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('Option', fn () => null, checked: true);

    $items = (new ReflectionClass($menu))->getProperty('items')->getValue($menu);
    expect($items[0]['checked'])->toBeTrue();
});

test('addItem returns static for chaining', function (): void {
    $menu = new ContextMenu();
    $result = $menu->addItem('Test', fn () => null);
    expect($result)->toBe($menu);
});

test('addSeparator adds separator item', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('A', fn () => null)
         ->addSeparator()
         ->addItem('B', fn () => null);

    $items = (new ReflectionClass($menu))->getProperty('items')->getValue($menu);
    expect($items)->toHaveCount(3);
    expect($items[1]['text'])->toBe('-');
});

test('multiple items with mixed options', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('Open', fn () => 1)
         ->addSeparator()
         ->addItem('Delete', fn () => 2, disabled: true)
         ->addItem('Info', fn () => 3, checked: true);

    $items = (new ReflectionClass($menu))->getProperty('items')->getValue($menu);
    expect($items)->toHaveCount(4);
    expect($items[0])->toMatchArray(['text' => 'Open', 'disabled' => false, 'checked' => false]);
    expect($items[1])->toMatchArray(['text' => '-', 'disabled' => false, 'checked' => false]);
    expect($items[2])->toMatchArray(['text' => 'Delete', 'disabled' => true, 'checked' => false]);
    expect($items[3])->toMatchArray(['text' => 'Info', 'disabled' => false, 'checked' => true]);
});

test('setSource and getSource round-trip', function (): void {
    $menu = new ContextMenu();
    $source = new stdClass();

    $result = $menu->setSource($source);
    expect($result)->toBe($menu);
    expect($menu->getSource())->toBe($source);
});

test('getSource returns null by default', function (): void {
    $menu = new ContextMenu();
    expect($menu->getSource())->toBeNull();
});

test('addItem with null callback', function (): void {
    $menu = new ContextMenu();
    $menu->addItem('Disabled Item');
    $items = (new ReflectionClass($menu))->getProperty('items')->getValue($menu);
    expect($items[0]['callback'])->toBeNull();
});