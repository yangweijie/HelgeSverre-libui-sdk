# Pickers

Modal dialogs for picking values. All use a **nested event-loop step** (`uiMainStep(1)`) — they do NOT call `uiQuit()`, so they can be called from within an already-running `uiMain()` loop. All accept an optional parent Window parameter for centering.

| Class | Returns | Description |
|---|---|---|
| `ColorPickerDialog` | `?Color` | Wraps `ColorButton` in a temp modal window |
| `FontPickerDialog` | `?FontDescriptor` | Wraps `FontButton` in a temp modal window |
| `DatePickerDialog` | `?\DateTimeImmutable` | Date-only picker (no time) |
| `TimePickerDialog` | `?\DateTimeImmutable` | Time-only picker (no date) |

```php
$color = ColorPickerDialog::pick($mainWindow);
if ($color !== null) {
    // use color
}

$font = FontPickerDialog::pick($mainWindow);
if ($font !== null) {
    // use font descriptor
}

$date = DatePickerDialog::pick($mainWindow);
$time = TimePickerDialog::pick($mainWindow);
```
