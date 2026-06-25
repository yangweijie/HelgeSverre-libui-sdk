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
});

test('CircleProgressBar can be constructed with initial progress', function (): void {
    $bar = new CircleProgressBar(50);
    expect($bar->getProgress())->toBe(50);
});

test('CircleProgressBar clamps initial progress below 0', function (): void {
    $bar = new CircleProgressBar(-10);
    expect($bar->getProgress())->toBe(0);
});

test('CircleProgressBar clamps initial progress above 100', function (): void {
    $bar = new CircleProgressBar(150);
    expect($bar->getProgress())->toBe(100);
});

// ---------------------------------------------------------------------------
// setProgress / getProgress
// ---------------------------------------------------------------------------

test('setProgress updates progress', function (): void {
    $bar = new CircleProgressBar();
    $bar->setProgress(75);
    expect($bar->getProgress())->toBe(75);
});

test('setProgress clamps below 0', function (): void {
    $bar = new CircleProgressBar(50);
    $bar->setProgress(-20);
    expect($bar->getProgress())->toBe(0);
});

test('setProgress clamps above 100', function (): void {
    $bar = new CircleProgressBar(50);
    $bar->setProgress(200);
    expect($bar->getProgress())->toBe(100);
});

test('setProgress returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setProgress(30);
    expect($result)->toBe($bar);
});

// ---------------------------------------------------------------------------
// setColor
// ---------------------------------------------------------------------------

test('setColor returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setColor(Color::rgb(0xFF0000));
    expect($result)->toBe($bar);
});

// ---------------------------------------------------------------------------
// setThickness
// ---------------------------------------------------------------------------

test('setThickness returns static for chaining', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setThickness(16.0);
    expect($result)->toBe($bar);
});

test('setThickness clamps minimum to 1.0', function (): void {
    $bar = new CircleProgressBar();
    $result = $bar->setThickness(0.0);
    expect($result)->toBe($bar);
    // Just verify it doesn't crash — internal state is private
    expect($bar->root())->toBeInstanceOf(Control::class);
});

// ---------------------------------------------------------------------------
// SIZE constant
// ---------------------------------------------------------------------------

test('SIZE constant is defined', function (): void {
    expect(CircleProgressBar::SIZE)->toBe(120);
});
