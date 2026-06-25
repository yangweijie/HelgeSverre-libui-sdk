# Findings — Composite GUI Components

## Requirements
- Group 1: TextField, PasswordField, NumberField, SearchField — Label + input, HasValue, 'change' event
- Group 2: FilePickerField (Entry + Browse), SliderField (Slider + value label)
- All extend `Yangweijie\Ui2\Composite`, use `EmitsEvents` trait
- All bundle into `Yangweijie\Ui2\Fields` namespace at `src/Fields/`

## Available Upstream Controls
| Control | Constructor | Value API | Change Event |
|---------|-------------|-----------|-------------|
| Label(string) | Label(string $text) | text()/setText() | none |
| Entry | Entry() | text()/setText() | onChanged(callable) |
| Entry::password() | static — password entry | same as Entry | same |
| Entry::search() | static — search entry | same as Entry | same |
| Spinbox(int, int) | Spinbox($min, $max) | value()/setValue(int) | onChanged(callable) |
| Slider(int, int) | Slider($min, $max) | value()/setValue(int) | onChanged + onReleased |
| Button(string) | Button($text) | text()/setText() | onClicked(callable) |

## Design Pattern
Each field:
- `root()` → Box (horizontal: Label + stretchy control)
- `value()` → delegates to inner control
- `setValue(mixed)` → delegates to inner control
- Constructor wires upstream onChanged → `$this->emit('change', $this->value())`

## FilePickerField Design
- Constructor: `__construct(string $label, string $mode = 'open', ?Window $parent = null)`
- Internal: Box(Label + Entry(read-only) + Button("Browse"))
- Browse button calls Dialogs::for($parent)->openFile()/saveFile()
- Sets Entry text from returned path, emits 'change'

## SliderField Design  
- Constructor: `__construct(string $label, int $min, int $max, int $initial = 0, bool $showTooltip = true)`
- Internal: Box(Label + Slider + StaticLabel(value))
- onChange → updates value label + emit 'change'
- onRelease → emit 'released'
