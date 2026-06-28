# Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Build;
use Yangweijie\Ui2\Fields\TextField;

Ffi::init();

// A labelled text input with change events
$name = new TextField('Name:', 'World');
$name->on('change', fn (string $val) => print("Hello, {$val}!\n"));

$window = new Window('ui2 Demo', 400, 200);
$window->setMargined(true);
$window->setChild(
    Build::vbox(
        $name,
        new Label('Type in the field above'),
    ),
);
$window->run();
```

## Key Patterns

- Call `Libui\Ffi::init()` before any widget constructor (idempotent)
- For multi-window apps, use `Libui\App::run()` instead of `Window::run()`
- Event callbacks return `void`; use try/catch for error handling inside callbacks
- Closures passed to libui callbacks are retained — no need to keep references yourself
