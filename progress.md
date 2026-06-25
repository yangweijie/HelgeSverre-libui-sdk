# Progress Log

## Session: 2026-06-25

### Phase 0: AGENTS.md Rewrite ✅
- **Status:** complete
- Rewrote from scratch with project structure, patch table, upstream essentials

### Phase 1: Group 1 — Form Fields ✅
- **Status:** complete
- **Files created:** TextField.php, PasswordField.php, NumberField.php, SearchField.php
- All follow Composite base + EmitsEvents pattern

### Phase 2: Group 2 — Picker & Slider Fields ✅
- **Status:** complete
- **Files created:** FilePickerField.php (Entry + Browse), SliderField.php (Slider + value label)
- FilePickerField uses Dialogs::for($parent)->openFile() for native dialog

### Phase 3: Verify & Commit ✅
- **Status:** complete

### Phase 4: Pest Migration & Documentation ✅
- **Status:** complete
- README.md, Pest migration (composer.json, tests/Pest.php, DialogsTest.php conversion), AGENTS.md updated

### Phase 5: Gap Analysis — Container Patches ✅
- **Status:** complete
- **Files created:** patches/helgesverre/libui/src/Group.php, patches/helgesverre/libui/src/Tab.php
- Group: setChild() accepts Control|Composite, titled() factory
- Tab: append()/appendMargined() accept Control|Composite, pages()

### Phase 6: Gap Analysis — New Field Composites ✅
- **Status:** complete
- **Files created:** CheckboxField.php, RadioGroup.php, ComboBoxField.php, EditableComboBoxField.php, DatePickerField.php
- All in src/Fields/ namespace, implement HasValue, emit 'change'

### Phase 7: Gap Analysis — Functional Widgets ✅
- **Status:** complete
- **Files created:** TextAreaField.php (MultilineEntry, vertical), ProgressBarField.php (setProgress/indeterminate), SeparatorLine.php (horizontal divider)

### Phase 8: Gap Analysis — Dialog Helpers ✅
- **Status:** complete
- **Files created:** src/Dialogs/MessageBox.php — static info()/warning()/error()

### Phase 9: Gap Analysis — Area-based Custom Widgets ✅
- **Status:** complete
- **Files created:** src/Widgets/ToggleSwitch.php (Area-based toggle, 'change' event), src/Widgets/StatusIndicator.php (colored dot, setColor/setColorHex)

### Phase 10: Documentation ✅
- **Status:** complete
- AGENTS.md — updated patch table, fields table, new Dialogs/Widgets sections
- README.md — updated patch table, fields table, new Dialogs/Widgets sections

### Phase 11: Testing — Pest Test Suite ✅
- **Status:** complete
- **Files created:** tests/FieldsTest.php (34 tests), tests/WidgetsTest.php (8 tests)
- **Files modified:** tests/DialogsTest.php (removed `setAccessible`, removed FFI dependency)
- FieldsTest: covers all 12 fields (constructor, setValue, emit, root)
- WidgetsTest: ToggleSwitch (default/initial/setValue/root), StatusIndicator (color/setColor/setColorHex/root)
- DialogsTest: 4 tests via ReflectionClass::newInstanceWithoutConstructor — zero FFI interaction
- Known: zend_mm_heap corrupted when all 3 files in one PHPUnit process (FFI GC conflict)

### Phase 12: Demo + Runtime Fixes ✅
- **Status:** complete
- **Files created:** examples/all-components.php — 4-tab demo app
- **Runtime fixes applied:**
  1. Group::titled() requires 2 args (title + child) — fixed 5 call sites
  2. App::run() returns void — restructured with $mainWindow ref
  3. Build::hbox() rejects Composite — added ->root() on ToggleSwitch/StatusIndicator
  4. Private const → public const in ToggleSwitch/StatusIndicator
  5. Dialogs::msgBoxError() → ::error()
  6. ToggleSwitch: `use Libui\StrokeParams` → `use Libui\Draw\StrokeParams`
  7. FontDescriptor: `$font->family` → `$font->family()` (methods not properties)

## Summary
- **Phase 0-12**: 16 new files, 7 modified files, 46 total tests, Phase 12 demo 4-tab
- **Phase 13**: WebView port — 7 files (bridge/*.m/*.c/*.dll/.dylib/.so/README.md, src/WebView.php, examples/webview.php), rpath fix for PebView.dylib
- **Phase 14**: Dialog/picker centering — centeredOn() in Window patch, SeparatorLine __destruct, 6 picker/dialog files updated
- **Phase 15**: 4 new widgets — CircleProgressBar (Area-based), Toast (PebView FFI), TreeView (WebView+html), CodeEditor (WebView+highlight.js html)
- **Phase 16**: all-components.php rewritten to 6 tabs, GC rewrite (no more inline temporaries), namespace fix (Widget\Button → Button), Brush::color() fix in progress

## Runtime Fixes (Phase 12-16 Cumulative)
1. Group::titled() requires 2 args — fixed 5 call sites
2. App::run() returns void — restructured with $mainWindow ref
3. Build::hbox() rejects Composite — added ->root()
4. Private const → public const in ToggleSwitch/StatusIndicator
5. Dialogs::msgBoxError() → ::error()
6. ToggleSwitch: `use Libui\StrokeParams` → `use Libui\Draw\StrokeParams`
7. FontDescriptor: `$font->family` → `$font->family()`
8. Bridge dylib rpath: rebuilt PebView.dylib from source, recompiled bridge
9. GC dangling-pointer: all-components.php rewritten, no inline temporaries
10. Namespace: `Libui\Widget\Button` → `Libui\Button`
11. CircleProgressBar: `Color` → `Brush::color()` in strokePath() calls
