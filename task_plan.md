# Task Plan: Composite GUI Components

## Goal
Build a set of reusable Composite-based GUI components in `src/Fields/`, plus patches, dialogs, and custom widgets. Complete implementation of the 5-phase gap analysis derived from `software—arch.md`.

## Current Phase
Complete (all phases done)

## Phases

### Phase 0: AGENTS.md Rewrite
- [x] Rewrite AGENTS.md with project structure, patch table, upstream essentials
- **Status:** complete

### Phase 1: Group 1 — Form Fields (TextField, PasswordField, NumberField, SearchField)
- [x] Create `src/Fields/` directory
- [x] `src/Fields/TextField.php` — Label + Entry, HasValue(string), emits 'change'
- [x] `src/Fields/PasswordField.php` — Label + PasswordEntry, HasValue(string), emits 'change'
- [x] `src/Fields/NumberField.php` — Label + Spinbox, HasValue(int), emits 'change'
- [x] `src/Fields/SearchField.php` — Label + SearchEntry, HasValue(string), emits 'change'
- [x] PHP lint all files
- **Status:** complete

### Phase 2: Group 2 — Picker & Slider Fields
- [x] `src/Fields/FilePickerField.php` — Entry(readonly) + "Browse" button, opens native file dialog
- [x] `src/Fields/SliderField.php` — Slider + value label, updates label on drag
- [x] PHP lint all files
- **Status:** complete

### Phase 3: Verify & Commit
- [x] Run php -l on all new files
- [x] Run existing tests
- [x] Git commit
- **Status:** complete

### Phase 4: Pest Migration & Documentation
- [x] README.md — comprehensive project documentation
- [x] composer.json — phpunit/phpunit → pestphp/pest ^4.0
- [x] tests/Pest.php — Pest config file
- [x] tests/DialogsTest.php — PHPUnit → Pest test functions
- [x] AGENTS.md — updated testing section
- **Status:** complete

### Phase 5: Gap Analysis — Container Patches
- [x] `patches/helgesverre/libui/src/Group.php` — Composite support, titled() factory
- [x] `patches/helgesverre/libui/src/Tab.php` — Composite support in append()/appendMargined()
- **Status:** complete

### Phase 6: Gap Analysis — New Field Composites
- [x] `src/Fields/CheckboxField.php` — Label + Checkbox, bool value
- [x] `src/Fields/RadioGroup.php` — RadioButtons wrapper, int value, addOptions()
- [x] `src/Fields/ComboBoxField.php` — Label + Combobox, int selected index
- [x] `src/Fields/EditableComboBoxField.php` — Label + EditableCombobox, string value
- [x] `src/Fields/DatePickerField.php` — Label + DateTimePicker, DateTimeImmutable, dateOnly()/timeOnly()
- **Status:** complete

### Phase 7: Gap Analysis — Functional Widgets
- [x] `src/Fields/TextAreaField.php` — Label + MultilineEntry, vertical layout
- [x] `src/Fields/ProgressBarField.php` — Label + ProgressBar, setProgress()/indeterminate()
- [x] `src/Fields/SeparatorLine.php` — Horizontal separator
- **Status:** complete

### Phase 8: Gap Analysis — Dialog Helpers
- [x] `src/Dialogs/MessageBox.php` — Static info()/warning()/error()
- **Status:** complete

### Phase 9: Gap Analysis — Area-based Custom Widgets
- [x] `src/Widgets/ToggleSwitch.php` — Area-based toggle, emits 'change'
- [x] `src/Widgets/StatusIndicator.php` — Area-based colored dot, setColor()/setColorHex()
- **Status:** complete

### Phase 10: Documentation
- [x] AGENTS.md — updated patch table, fields table, new Dialogs/Widgets sections
- [x] README.md — updated patch table, fields table, new Dialogs/Widgets sections
- **Status:** complete

### Phase 11: Testing — Pest Test Suite
- [x] `tests/FieldsTest.php` — 34 tests for all 12 field composites (constructor, setValue, emit, root)
- [x] `tests/WidgetsTest.php` — 8 tests for ToggleSwitch + StatusIndicator (construction, state, root)
- [x] `tests/DialogsTest.php` — 4 tests using ReflectionClass::newInstanceWithoutConstructor (zero FFI, passes alone)
- [x] Remove `beforeAll(Ffi::init())` from FieldsTest/WidgetsTest (widget constructors init FFI internally)
- [x] Remove deprecated `setAccessible(true)` from DialogsTest (no-op since 8.1, deprecated in 8.5)
- **Status:** complete

### Phase 12: Demo + Runtime Fixes
- [x] `examples/all-components.php` — 4-tab demo (Fields/Custom/Dialogs/Pickers)
- [x] Fix `Group::titled()` 2-arg requirement (5 call sites)
- [x] Fix `App::run()` returns void (restructure: create Window first, pass to App)
- [x] Fix `Build::hbox()` rejects Composite (add ->root() on ToggleSwitch/StatusIndicator)
- [x] Fix private const access (ToggleSwitch/StatusIndicator consts to public)
- [x] Fix `Dialogs::msgBoxError()` → `->error()`
- [x] Fix `use Libui\StrokeParams` → `use Libui\Draw\StrokeParams`
- [x] Fix FontDescriptor property access (`$font->family` → `$font->family()`, `$font->size` → `$font->size()`)
- **Status:** complete

## Key Questions
1. ~~Should FilePickerField accept a parent Window or hold its own reference?~~ ✅ (ref in constructor)
2. ~~Should SliderField update label in real-time or only on release?~~ ✅ (both)
3. [NEW] Why does `zend_mm_heap corrupted` occur when all 3 test files run in one PHPUnit process?
   - Root cause: FFI GC conflict. `ReflectionClass::newInstanceWithoutConstructor` in DialogsTest creates a Window without FFI handle. When PHPUnit collects all objects across files, the orphaned FFI dependent class triggers heap corruption. **Workaround:** run test files individually or with `--filter`.

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| All fields use horizontal Box(root) = Label + Input | Consistent form layout |
| Fields use EmitsEvents trait for 'change' event | Bridges upstream onChanged → Composite event model |
| FilePickerField accepts Window in constructor | Dialogs need parent for native modal |
| SliderField updates label on both onChange AND onRelease | Real-time feedback + final value accuracy |
| RadioGroup/ComboBoxField/EditableComboBoxField: options via addOptions() | Matches upstream incremental append pattern |
| DatePickerField: constructor takes optional pre-configured DateTimePicker | Flexibility for dateOnly/timeOnly |
| ProgressBarField: no value() — read-only display | ProgressBar is display-only in libui |
| ToggleSwitch: uses Area + internal ToggleDelegate | Custom drawing requires Area |
| DialogsTest uses newInstanceWithoutConstructor (no FFI) | Avoids native libui dependency in unit tests |
| Demo uses `App::run()` for layout | Cleaner window construction pattern |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| Group::titled() "too few arguments" | 1 | Changed to `Group::titled('title')->setChild(...)` |
| App::run() returns void, not Window | 1 | Create Window first, store in `$mainWindow` ref, pass to `App::new()->window()` |
| Build::hbox() rejects Composite arg | 1 | Added `->root()` on ToggleSwitch/StatusIndicator args |
| Private const access in ToggleDelegate::draw() | 1 | Changed `private const` → `public const` |
| "Call to undefined method Dialogs::msgBoxError()" | 1 | Upstream has `error()` not `msgBoxError()` |
| "Class Libui\StrokeParams not found" at runtime | 1 | Wrong namespace: `Libui\StrokeParams` → `Libui\Draw\StrokeParams` |
| "Undefined property FontDescriptor::$family" | 1 | FontDescriptor uses methods `family()`/`size()`, not public properties |
| zend_mm_heap corrupted (all 3 test files) | 1 | FFI GC conflict with newInstanceWithoutConstructor. Run files individually. |
