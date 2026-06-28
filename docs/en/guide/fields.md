# Fields

Each Field is a `Composite` that pairs a `Label` with a specific input widget in a horizontal row:

| Class | Input widget | Value type | Notes |
|---|---|---|---|
| `TextField` | `Entry` | `string` | Simple text input |
| `SearchField` | `Entry::search()` | `string` | Search-style field; may debounce on macOS |
| `PasswordField` | `Entry::password()` | `string` | Text masked on screen, readable via `value()` |
| `NumberField` | `Spinbox` | `int` | Requires min/max range |
| `SliderField` | `Slider` | `int` | Has live value readout label |
| `FilePickerField` | `Entry` (readonly) + `Button` | `string` | Requires parent `Window`; opens native file dialog |
| `CheckboxField` | `Checkbox` | `bool` | Checkbox with label |
| `RadioGroup` | `RadioButtons` | `int` | Selected index (0-based); `addOptions()` |
| `ComboBoxField` | `Combobox` | `int` | Selected index (0-based); `addOptions()` |
| `EditableComboBoxField` | `EditableCombobox` | `string` | User-typable combo; `addOptions()` |
| `DatePickerField` | `DateTimePicker` | `\DateTimeImmutable` | `dateOnly()`/`timeOnly()` factories |
| `TextAreaField` | `MultilineEntry` | `string` | Vertical label + stretchy text area |
| `ProgressBarField` | `ProgressBar` | (none) | `setProgress()`, `indeterminate()` |
| `SeparatorLine` | `Separator` | (none) | Thin horizontal divider |

```php
$field = new TextField('Name:', 'default');
$field->on('change', fn (string $val) => print($val));
$form->append($field->root(), 'Name:');

// Get/set value
$val = $field->value();
$field->setValue('New value');
```
