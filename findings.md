# Findings — Composite GUI Components

## Requirements
- Group 1: TextField, PasswordField, NumberField, SearchField — Label + input, HasValue, 'change' event
- Group 2: FilePickerField (Entry + Browse), SliderField (Slider + value label)
- Gap Analysis phases 1-5: 13 new files across patches, fields, dialogs, widgets
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
| Checkbox(string) | Checkbox($text) | checked()/setChecked(bool)/onToggled | onToggled |
| RadioButtons | RadioButtons() | selected()/setSelected(int) | onSelected |
| Combobox | Combobox() | selected()/setSelected(int) | onSelected |
| EditableCombobox | EditableCombobox() | text()/setText(string) | onChanged |
| DateTimePicker | DateTimePicker() | `\DateTime` value / setValue(`\DateTime`) | onChanged |
| MultilineEntry | MultilineEntry() | text()/setText(string) | onChanged |
| ProgressBar | ProgressBar() | value()/setValue(int 0-100) | none (read-only) |
| Separator(uiSeparatorOrientationHorizontal) | Separator() | none | none |

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

## Container Patch Design
- All container patches (Box, Form, Grid, Group, Tab) accept `Control|Composite`
- `$child instanceof Composite ? $child->root() : $child` unwrapping pattern
- Group::titled() — static factory for titled groups
- Tab::append($label, Control|Composite, $margined = false)
- Tab::pages() — returns array of [label, Control]

## Custom Widget Design
- ToggleSwitch: Area-based using fillCircle/strokeCircle, ToggleDelegate internal class
- StatusIndicator: Area-based colored circle, setColor(Color)/setColorHex(int)
- Both use the patched DrawContext::fillCircle/strokeCircle API

## Runtime Issues Discovered (Demo Run)
1. **Group::titled() is a factory requiring 2 args** — `Group::titled('Title', $child)` not `Group::titled('Title')->setChild($child)`. The factory creates and immediately sets the child.
2. **App::run() returns void** — `$window = App::new()->window(...)->run()` doesn't work. Create Window first, store ref, pass to `App::new()->window($window)->run()`.
3. **Build::hbox() doesn't accept Composite** — only upstream Control. Must call `->root()` explicitly for Composite widgets.
4. **ToggleSwitch/StatusIndicator constants must be public** — `ToggleDelegate::draw()` accesses them by class name from a different file (class in same file).
5. **FontDescriptor uses accessor methods** — `family()`/`size()`, not public properties.

## Test Isolation Requirement
DialogsTest must use `ReflectionClass::newInstanceWithoutConstructor()` to create a Window without FFI handle (passes alone). When run together with FieldsTest/WidgetsTest (which use real FFI widgets), PHP's GC gets confused and corrupts the `zend_mm_heap`. This is a known PHP FFI limitation — each test file runs fine individually.

## FFI Autoloading
Widget constructors (Entry, Spinbox, Slider, etc.) internally call `Ffi::get()` → `self::init()` which is idempotent. No `beforeAll(Ffi::init())` is needed in test files. Removing it avoids cross-test-file FFI re-initialization issues.

## WebView Bridge
- `src/WebView.php` wraps two FFI libraries: bridge (wvb_create/move/destroy) + PebView (webview_set_html/navigate/bind/return/eval)
- macOS bridge requires `@rpath` pointing to PebView.dylib location in `vendor/kingbes/pebview/lib/macos/arm64/`
- Bridge source: `bridge/webview_bridge.m` (ObjC WKWebView wrapper), compiled with -rpath flag
- PebView.dylib is built from kingbes/pebview source via `pebview/macos.sh`

## Circular Progress Bar Drawing
- `DrawContext::strokePath()` takes `Brush $brush`, NOT `Color`
- Must wrap via `Brush::color(Color $color)` or use shorthand `Brush::rgb(int $hex)`
- The patched DrawContext has convenience methods (strokeArc, strokeCircle, etc.) that accept `Brush|Color$` and auto-convert

## GC Issue with Temporary Composite Objects
- PHP destroys temporary objects at statement end
- If a `Composite` has `__destruct()` that destroys its libui Control, but the Control is still in libui's widget tree, GC causes `uiControlVerifySetParent` errors
- **Fix**: always store Composite objects in named persistent variables — never use inline temporaries like `(new SeparatorLine())->root()` inside an array/argument list

## Upstream Namespace
- All upstream widget classes are directly in `Libui\` namespace (e.g. `Libui\Button`, `Libui\Entry`)
- There is NO `Libui\Widget\` sub-namespace — using it causes "Class not found" errors
