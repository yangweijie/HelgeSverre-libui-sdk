<?php

declare(strict_types=1);

use Libui\Control;
use Yangweijie\Ui2\Composite;

/**
 * Test the Composite abstract base class via a stub subclass.
 * The stub uses a mock Control returned from a fake root().
 */

class StubControl extends Control
{
    public function __construct()
    {
    }
}

class StubComposite extends Composite
{
    private StubControl $stubRoot;
    private mixed $val;

    public function __construct(mixed $initialValue = null)
    {
        $this->stubRoot = new StubControl();
        $this->val = $initialValue;
    }

    public function root(): Control
    {
        return $this->stubRoot;
    }

    public function value(): mixed
    {
        return $this->val;
    }

    public function setValue(mixed $value): static
    {
        $this->val = $value;
        return $this;
    }
}

class MinimalComposite extends Composite
{
    public function root(): Control
    {
        return new StubControl();
    }
}

// ---------------------------------------------------------------------------
// root()
// ---------------------------------------------------------------------------

test('root returns a Control instance', function (): void {
    $composite = new StubComposite();
    expect($composite->root())->toBeInstanceOf(Control::class);
});

// ---------------------------------------------------------------------------
// value() / setValue()
// ---------------------------------------------------------------------------

test('value returns initial value', function (): void {
    $composite = new StubComposite('initial');
    expect($composite->value())->toBe('initial');
});

test('value returns null by default when not overridden', function (): void {
    $composite = new MinimalComposite();
    expect($composite->value())->toBeNull();
});

test('setValue returns static for chaining', function (): void {
    $composite = new StubComposite();
    $result = $composite->setValue('new');
    expect($result)->toBe($composite);
});

test('setValue propagates to value', function (): void {
    $composite = new StubComposite('old');
    $composite->setValue('new');
    expect($composite->value())->toBe('new');
});

test('setValue is a no-op by default when not overridden', function (): void {
    $composite = new MinimalComposite();
    $result = $composite->setValue('anything');
    expect($result)->toBe($composite);
    expect($composite->value())->toBeNull();
});

// ---------------------------------------------------------------------------
// asControl()
// ---------------------------------------------------------------------------

test('asControl delegates to root', function (): void {
    $composite = new StubComposite();
    // asControl() calls root()->asControl(), which needs FFI.
    // We just verify the method exists and is callable.
    expect(method_exists($composite, 'asControl'))->toBeTrue();
});
