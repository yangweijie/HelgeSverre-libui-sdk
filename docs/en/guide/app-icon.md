# App Icon

Set the dock/taskbar icon from a PNG file:

```php
$window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
```

## At Startup

To set the icon immediately at startup (before the event loop draws the window), use `App::afterInit()`:

```php
use Libui\App;
use Libui\Ffi;
use Libui\Window;

Ffi::init();
$window = new Window('My App', 600, 400);

App::afterInit(function () use ($window): void {
    $window->setWindowIcon(__DIR__ . '/assets/app-icon.png');
});

$window->run();
```

## Platform Details

- **macOS**: Uses `NSApp setApplicationIconImage:` via the bridge dylib
- **Linux**: Uses PebView `set_icon()`
- **Windows**: Uses PebView `set_icon()`
