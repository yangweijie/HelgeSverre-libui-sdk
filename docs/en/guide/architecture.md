# Architecture

## Composite Pattern

The core abstraction is `Composite` — an abstract base for widgets built from multiple controls. A `Composite` wraps one or more child controls behind a single `root()` method so the whole group can be added to containers (`Box`, `Form`, `Grid`) as if it were a single widget.

```php
abstract class Composite implements HasValue
{
    abstract public function root(): Control;
    public function value(): mixed { /* override in subclasses */ }
    public function setValue(mixed $value): static { /* override */ }
}
```

All container patches (`Box`, `Form`, `Grid`, `Group`, `Tab`) accept `Composite` children transparently — they call `$composite->root()` internally.

## EmitsEvents Trait

A lightweight event emitter trait. Drop it into any class to add `on(event, handler)` / `emit(event, data)`.

```php
class MyWidget extends Composite
{
    use EmitsEvents;

    public function doSomething(): void
    {
        $this->emit('change', $this->value());
    }
}

$widget->on('change', fn ($val) => print("Changed: {$val}"));
```

All Field composites use this trait and emit `'change'` when the input value changes.

## Important: Composite GC Trap

Temporary `Composite` objects (e.g. `(new SeparatorLine())->root()`) get `__destruct()` called at statement end via PHP's GC, which calls `uiControlDestroy()` on the underlying C widget while libui still holds a reference. **Always store Composites in named persistent variables.**

If you see `uiControlVerifySetParent` errors, this is the cause.
