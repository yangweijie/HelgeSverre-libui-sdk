# Menus

Two APIs coexist. Menus **must** be created before the first `Window` (enforced at runtime via `MenuOrderException`).

## Declarative / Fluent Style

```php
Menu::create('File')
    ->item('Open', fn (MenuItem $item) => /* ... */)
    ->separator()
    ->quitItem();

Menu::create('Edit')
    ->checkItem('Dark Mode', fn (MenuItem $item) => /* ... */);
```

## Imperative Style

```php
$help = new Menu('Help');
$about = $help->appendAboutItem();
$about->onClick(fn (MenuItem $item) => /* ... */);
```

::: note
The patched `MenuItem::onClick()` replaces the handler on each call — it does NOT stack like most libui callbacks.
:::
