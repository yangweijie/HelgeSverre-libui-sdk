# Dialogs

| Class | Description |
|---|---|
| `MessageBox` | Static helpers: `info()`, `warning()`, `error()` — wraps upstream native msgBox API |
| `DialogConfirm` | `ask(Window, $title, $message): bool` — modal yes/no dialog |
| `DialogPrompt` | `ask(Window, $title, $label, $default): ?string` — modal text input dialog |

All modal dialogs accept an optional parent Window parameter; when provided, the dialog is centered on the parent window rather than screen-center.

```php
use Yangweijie\Ui2\Dialogs\MessageBox;
use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;

MessageBox::info($window, 'Saved', 'Document saved successfully.');

$confirmed = DialogConfirm::ask($window, 'Delete', 'Are you sure?');
if ($confirmed) {
    // proceed with delete
}

$name = DialogPrompt::ask($window, 'Input', 'Enter your name:', 'John');
if ($name !== null) {
    print("Hello, {$name}!");
}
```
