<?php

declare(strict_types=1);

use Libui\Color;
use Libui\Control;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\ToggleSwitch;



// ---------------------------------------------------------------------------
// ToggleSwitch
// ---------------------------------------------------------------------------

test('ToggleSwitch can be constructed with default off', function (): void {
    $toggle = new ToggleSwitch();
    expect($toggle->root())->toBeInstanceOf(Control::class);
    expect($toggle->value())->toBe(false);
});

test('ToggleSwitch can be constructed with initial on', function (): void {
    $toggle = new ToggleSwitch(true);
    expect($toggle->value())->toBe(true);
});

test('ToggleSwitch setValue toggles state', function (): void {
    $toggle = new ToggleSwitch();
    expect($toggle->value())->toBe(false);

    $toggle->setValue(true);
    expect($toggle->value())->toBe(true);

    $toggle->setValue(false);
    expect($toggle->value())->toBe(false);
});

test('ToggleSwitch root returns an Area (Control)', function (): void {
    $toggle = new ToggleSwitch();
    expect($toggle->root())->toBeInstanceOf(Control::class);
});

// ---------------------------------------------------------------------------
// StatusIndicator
// ---------------------------------------------------------------------------

test('StatusIndicator can be constructed with color', function (): void {
    $color = Color::rgb(0x22C55E);
    $indicator = new StatusIndicator($color);
    expect($indicator->root())->toBeInstanceOf(Control::class);
});

test('StatusIndicator setColor changes color', function (): void {
    $green = Color::rgb(0x22C55E);
    $red = Color::rgb(0xEF4444);
    $indicator = new StatusIndicator($green);

    $result = $indicator->setColor($red);
    expect($result)->toBe($indicator);
    expect($indicator->root())->toBeInstanceOf(Control::class);
});

test('StatusIndicator setColorHex convenience method', function (): void {
    $indicator = new StatusIndicator(Color::rgb(0x22C55E));
    $result = $indicator->setColorHex(0xEF4444);
    expect($result)->toBe($indicator);
    expect($indicator->root())->toBeInstanceOf(Control::class);
});

test('StatusIndicator root returns an Area (Control)', function (): void {
    $indicator = new StatusIndicator(Color::rgb(0x22C55E));
    expect($indicator->root())->toBeInstanceOf(Control::class);
});
