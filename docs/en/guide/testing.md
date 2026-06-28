# Testing

```bash
vendor/bin/pest
```

The project uses **Pest 4** (built on PHPUnit 12). Test configuration is in `phpunit.xml`.

## Test Structure

- `tests/Pest.php` — Pest configuration
- `tests/DialogsTest.php` — Tests an upstream private method via reflection (no FFI needed)

## Testing Utilities

- `Libui\Testing\CallbackSpy` — Assertion-based callback verification without an event loop
- `Libui\Testing\Inspect` — Widget inspection helpers
- `Window::resetMenuLock()` — Available for tests that need to create menus after a Window

## Writing Tests

Write new tests in Pest style:

```php
test('text field emits change event', function () {
    $field = new TextField('Name:', 'default');
    $spy = new CallbackSpy();
    $field->on('change', $spy);
    $field->setValue('New');
    expect($spy)->toHaveBeenCalled();
});
```
