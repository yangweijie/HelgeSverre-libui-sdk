<?php

declare(strict_types=1);

use Yangweijie\Ui2\EmitsEvents;

/**
 * Test the EmitsEvents trait via a lightweight stub class.
 * This avoids any FFI or widget construction — pure PHP logic.
 */

class StubEmitter
{
    use EmitsEvents;

    public function fire(string $event, mixed $data = null): void
    {
        $this->emit($event, $data);
    }
}

// ---------------------------------------------------------------------------
// Registration
// ---------------------------------------------------------------------------

test('on returns static for chaining', function (): void {
    $emitter = new StubEmitter();
    $result = $emitter->on('click', fn () => null);
    expect($result)->toBe($emitter);
});

test('multiple handlers for same event are called in order', function (): void {
    $emitter = new StubEmitter();
    $order = [];

    $emitter->on('test', function () use (&$order) {
        $order[] = 'first';
    });
    $emitter->on('test', function () use (&$order) {
        $order[] = 'second';
    });

    $emitter->fire('test');
    expect($order)->toBe(['first', 'second']);
});

// ---------------------------------------------------------------------------
// Emission
// ---------------------------------------------------------------------------

test('emit calls handler with data', function (): void {
    $emitter = new StubEmitter();
    $received = null;

    $emitter->on('change', function (mixed $data) use (&$received) {
        $received = $data;
    });

    $emitter->fire('change', 'hello');
    expect($received)->toBe('hello');
});

test('emit calls handler with null when no data', function (): void {
    $emitter = new StubEmitter();
    $received = 'sentinel';

    $emitter->on('event', function (mixed $data) use (&$received) {
        $received = $data;
    });

    $emitter->fire('event');
    expect($received)->toBeNull();
});

test('emit does nothing when no handlers registered', function (): void {
    $emitter = new StubEmitter();
    // Should not throw
    $emitter->fire('nonexistent', 'data');
    expect(true)->toBeTrue();
});

test('handlers for different events are independent', function (): void {
    $emitter = new StubEmitter();
    $aCalled = false;
    $bCalled = false;

    $emitter->on('a', function () use (&$aCalled) {
        $aCalled = true;
    });
    $emitter->on('b', function () use (&$bCalled) {
        $bCalled = true;
    });

    $emitter->fire('a');
    expect($aCalled)->toBeTrue();
    expect($bCalled)->toBeFalse();
});

test('emit passes complex data types', function (): void {
    $emitter = new StubEmitter();
    $received = null;

    $emitter->on('data', function (mixed $data) use (&$received) {
        $received = $data;
    });

    $payload = ['key' => 'value', 'nested' => [1, 2, 3]];
    $emitter->fire('data', $payload);
    expect($received)->toBe($payload);
});
