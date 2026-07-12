<?php

declare(strict_types=1);

use Yangweijie\Ui2\System\AudioCapability;
use Yangweijie\Ui2\System\Capability;
use Yangweijie\Ui2\System\CapabilityException;
use Yangweijie\Ui2\System\CapabilityRegistry;
use Yangweijie\Ui2\System\HotkeyCapability;
use Yangweijie\Ui2\System\ProcessCapability;
use Yangweijie\Ui2\System\SystemInfoCapability;
use Yangweijie\Ui2\System\TrayCapability;

//
// Interface contract: name() always returns the correct name.
//

test('AudioCapability name', function () {
    expect((new AudioCapability())->name())->toBe('audio');
});

test('TrayCapability name', function () {
    expect((new TrayCapability())->name())->toBe('tray');
});

test('HotkeyCapability name', function () {
    expect((new HotkeyCapability())->name())->toBe('hotkey');
});

test('SystemInfoCapability name', function () {
    expect((new SystemInfoCapability())->name())->toBe('systeminfo');
});

test('ProcessCapability name', function () {
    expect((new ProcessCapability())->name())->toBe('process');
});

//
// Interface contract: available() + reason() are consistent.
//

test('available + reason are consistent', function (Capability $cap) {
    $avail = $cap->available();
    $reason = $cap->reason();
    if ($avail) {
        expect($reason)->toBeNull('available caps should have null reason');
    } else {
        expect($reason)->not->toBeNull('unavailable caps should have a reason');
        expect($cap->dependencies())->not->toBeEmpty();
    }
})->with(function () {
    return [
        new AudioCapability(),
        new TrayCapability(),
        new HotkeyCapability(),
        new SystemInfoCapability(),
        new ProcessCapability(),
    ];
});

//
// dependencies() is always an array
//

test('dependencies is always an array', function (Capability $cap) {
    expect($cap->dependencies())->toBeArray();
})->with(fn () => [
    new AudioCapability(),
    new TrayCapability(),
    new HotkeyCapability(),
    new SystemInfoCapability(),
    new ProcessCapability(),
]);

//
// Pure-PHP capabilities are available (they depend only on composer packages).
//

test('SystemInfoCapability is available', function () {
    $cap = new SystemInfoCapability();
    expect($cap->available())->toBeTrue();
});

test('ProcessCapability is available', function () {
    $cap = new ProcessCapability();
    expect($cap->available())->toBeTrue();
});

//
// CapabilityRegistry — auto-registration + require
//

test('CapabilityRegistry has pure-PHP capabilities', function () {
    expect(CapabilityRegistry::has('systeminfo'))->toBeTrue();
    expect(CapabilityRegistry::has('process'))->toBeTrue();
});

test('CapabilityRegistry require() returns capability on success', function () {
    $cap = CapabilityRegistry::require('systeminfo');
    expect($cap)->toBeInstanceOf(SystemInfoCapability::class);
});

test('CapabilityRegistry require() throws for unknown caps', function () {
    CapabilityRegistry::require('nonexistent');
})->throws(CapabilityException::class, 'Unknown capability');

test('CapabilityRegistry names() returns registered names', function () {
    // Trigger auto-registration of all built-ins
    foreach (['audio', 'tray', 'hotkey', 'systeminfo', 'process'] as $name) {
        CapabilityRegistry::has($name);
    }

    $names = CapabilityRegistry::names();
    expect($names)->toContain('audio', 'tray', 'hotkey', 'systeminfo', 'process');
});

//
// Custom capability registration
//

test('CapabilityRegistry can register custom capabilities', function () {
    $fake = new class () implements \Yangweijie\Ui2\System\Capability {
        public function name(): string { return 'fake'; }
        public function available(): bool { return true; }
        public function reason(): ?string { return null; }
        public function dependencies(): array { return []; }
    };

    CapabilityRegistry::register($fake);
    expect(CapabilityRegistry::has('fake'))->toBeTrue();
    expect(CapabilityRegistry::names())->toContain('fake');
});

//
// require() with unavailable capability throws CapabilityException
//

test('require() with unavailable capability throws CapabilityException', function () {
    // Register an explicitly-unavailable cap to test require() reliably
    $unavailable = new class () implements \Yangweijie\Ui2\System\Capability {
        public function name(): string { return 'broken'; }
        public function available(): bool { return false; }
        public function reason(): ?string { return 'Test fixture is broken.'; }
        public function dependencies(): array { return ['test fixture is unavailable']; }
    };
    CapabilityRegistry::register($unavailable);

    CapabilityRegistry::require('broken');
})->throws(CapabilityException::class);
