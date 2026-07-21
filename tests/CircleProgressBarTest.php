<?php

declare(strict_types=1);

use Libui\Color;
use Libui\Control;
use Yangweijie\Ui2\Widgets\CircleProgressBar;

// ---------------------------------------------------------------------------
// Construction
// ---------------------------------------------------------------------------

test('CircleProgressBar can be constructed with default progress', function (): void {
    $bar = new CircleProgressBar();
    expect($bar->root())->toBeInstanceOf(Control::class);
    expect($bar->getProgress())->toBe(0);
})->group('ffi');

test('CircleProgressBar can be constructed with initial progress', function (): void {
    $bar = new CircleProgressBar(50);
    expect($bar->getProgress())->toBe(50);
})->group('ffi');

test('CircleProgressBar clamps initial progress below 0', function (): void {
    $bar = new CircleProgressBar(-10);
    expect($bar->getProgress())->toBe(0);
})->group('ffi');

test('CircleProgressBar clamps initial progress above 100', function (): void {
    $bar = new CircleProgressBar(150);
    expect($bar->getProgress())->toBe(100);
})->group('ffi');

// ---------------------------------------------------------------------------
// setProgress / getProgress
// ---------------------------------------------------------------------------

test('setProgress updates progress', function (): void {
    $bar = new CircleProgressBar();
    $bar->setProgress(75);
    expect($bar->getProgress())->toBe(75);
})->group('ffi');

test('setProgress clamps below 0', function (): void {
    $bar = new CircleProgressBar(50);
    $bar->setProgress(-20);
    expect($bar->getProgress())->toBe(0);
})->group('ffi');

test('setProgress clamps above 100', function (): void {
    $bar = new CircleProgressBar(50);
    $bar->setProgress(200);
    expect($bar->getProgress())->toBe(100);
})->group('ffi');

test('setProgress returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setProgress(30);
    expect($result)->toBe($bar);
})->group('ffi');

// ---------------------------------------------------------------------------
// setColor
// ---------------------------------------------------------------------------

test('setColor returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setColor(Color::rgb(0xFF0000));
    expect($result)->toBe($bar);
})->group('ffi');

// ---------------------------------------------------------------------------
// setThickness
// ---------------------------------------------------------------------------

test('setThickness returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setThickness(16.0);
    expect($result)->toBe($bar);
})->group('ffi');

test('setThickness clamps minimum to 1.0', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setThickness(0.0);
    expect($result)->toBe($bar);
    // Just verify it doesn't crash — internal state is private
    expect($bar->root())->toBeInstanceOf(Control::class);
})->group('ffi');


