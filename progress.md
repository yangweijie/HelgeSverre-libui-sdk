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

### Phase 13: WebView Port ✅
- **Status:** complete
- Bridge compiled with rpath, mouse/keyboard events enabled
- CodeEditor keyboard input fix: NSWindow makeKeyAndOrderFront + acceptsMouseMovedEvents

### Phase 14: Dialog/Picker Centering ✅
- **Status:** complete

### Phase 15: 4 New Widgets ✅
- **Status:** complete

### Phase 16: all-components.php 6-Tab Demo ✅
- **Status:** complete
- Window 800×600, CircleProgressBar vertical layout, TreeView/CodeEditor repositioned

### Phase 17: Test Coverage Improvement ✅
- **Status:** complete
- **New test files:** EmitsEventsTest.php (7), CompositeTest.php (7), CircleProgressBarTest.php (12), ToastTest.php (4), TableViewTest.php (19), LayoutTest.php (11), PickersTest.php (4), DialogsTest2.php (5)
- **Updated:** FieldsTest.php (+6: SliderField, FilePickerField)
- Total: 47 → 120 tests across 11 files

### Phase 18: Toast Error Handling ✅
- **Status:** complete
- Added `$lastError` static property + `lastError()` method to Toast.php
- Updated all-components.php to show error in UI label instead of STDERR

### Phase 19: WebView Bridge Fixes ✅
- **Status:** complete
- ARC fix: removed manual `[obj release]` calls
- Rpath fix: `-Wl,-rpath,$(pwd)/vendor/kingbes/pebview/lib/macos/arm64`
- Mouse/keyboard: `setAcceptsMouseMovedEvents:YES`, `setIgnoresMouseEvents:NO`, `makeKeyAndOrderFront:nil`

### Phase 20: WebView Eval Queue Mechanism ✅
- **Status:** complete
- **Root cause:** `webview_eval()` silently fails after `webview_set_html()` because page hasn't loaded
- **Fix:** `setHtml()` queues evals, flushes 300ms later via `Libui\Ffi::timer()`
- Verified: test-debug-bridge.php passes all 6 steps

### Phase 21: TreeView PHP ↔ WebView Communication ✅
- **Status:** complete
- **Root cause — eval queue:** `webview_eval()` silently fails after `webview_set_html()` because the page hasn't loaded a URI yet (webkit_web_view_get_uri() returns null → eval_impl() returns `{}`)
- **Fix — WebView eval queue:** Added `$pendingEval` + `$pageLoading` to WebView; `setHtml()` triggers 300ms deferred flush via `Libui\Ffi::timer()`. `eval()` queues when loading, executes directly otherwise. `flushPendingEval()` runs all queued JS.
- **TreeView communication fixes:**
  1. `setHtml()` override injects PebView init script into `<head>` so `window.__webview__` exists before `bind()` calls eval
  2. `createInitScript()` builds full PebView Webview JS with UUID-based promise system and platform postMessage
  3. `rebindHandlers()` re-registers click/toggle handlers after `setHtml()` (called from override)
  4. `bind()` uses `setTimeout(200ms)` to re-execute `onBind()` — ensures init script ran first
  5. `unbind()` also uses `setTimeout(200ms)` for clean teardown
  6. `tree-view.html`: `__onNodeClick`/`__onNodeToggle` defined as real functions (not null), with dynamic `window.__treeNodeClick` guard — no timing dependency on init script
  7. `bindNodeClick()` tracks `$selectedPath` automatically → `getSelectedPath()` reads PHP property, no eval round-trip
  8. Argument passing changed from JSON object to positional args (simpler, more reliable)
  9. CSS selector fix: `.tree-toggle` → `.tree-row > .tree-toggle`
- **Related fixes:**
  - Toast: `$lastError` static property + `lastError()` method
  - Bridge: ARC cleanup (removed `[obj release]`), mouse/keyboard (`acceptMouseMovedEvents`, `makeKeyAndOrderFront`)
  - `bridge/README.md`: added `-rpath` flag documentation

## Session: 2026-06-26

### Phase 22: CodeEditor Keyboard Focus Fix ✅
- **Status:** complete
- **Root cause:** `NSWindow` with `NSWindowStyleMaskBorderless` returns `NO` for `canBecomeKeyWindow` by default in Cocoa. Bridge's `makeKeyAndOrderFront:nil` silently fails — keyboard events never reach WKWebView's `<textarea>`.
- **Fix — new `KeyableWindow` subclass:** Added `KeyableWindow` NSWindow subclass in `bridge/webview_bridge.m` overriding `canBecomeKeyWindow` and `canBecomeMainWindow` to return `YES`. Used instead of plain `NSWindow` in `wvb_create()`.
- **Supplementary fixes:**
  - `assets/code-editor.html`: Added `autofocus` attribute + `editor.focus()` JS call after init
  - `src/Widgets/CodeEditor.php`: Added public `focus()` method, called from constructor
- **Bridge rebuilt:** `clang -shared -fobjc-arc` — 0 errors
- **Verification:** PHP syntax check on all changed files passed; ObjC `-fsyntax-only` passed

## Session: 2026-06-26 (Evening)

### Phase 23: TreeView Button Event Fixes ✅
- **Status:** complete
- **Files modified:**
  - `src/Widgets/TreeView.php` — getSelectedPath() tracks path in PHP; bindNodeClick/Toggle use positional args (not JSON.stringify)
  - `src/WebView.php` — revert flushPendingEval to simple version (no CData comparison bug)
  - `assets/tree-view.html` — remove `__treeNodeClick`/`__treeNodeToggle` no-ops; fix `:scope > .tree-toggle` → `.tree-row > .tree-toggle`; reset selectedPath in render()
  - `examples/test-treeview.php` — expand/collapse buttons now context-aware (use selected path)
- **Bugs found & fixed (4 issues):**
  1. `getSelectedPath()` returned TreeView instance → tracks path in PHP via nodeClick glue
  2. `req` parameter from webview_bind is JSON array of args, not JSON object → fixed positional arg handling
  3. HTML `__treeNodeClick` no-op prevented `onBind()` fallback from creating real function → removed no-ops
  4. `:scope > .tree-toggle` CSS selector failed (toggle inside `.tree-row`) → fixed to `.tree-row > .tree-toggle`

### Phase 24: CodeEditor CSS Fixes ✅
- **Status:** complete
- **Files modified:** `assets/code-editor.html`
- **Fixes:**
  - Added `background: #1e1e1e` to `.code-area` (fill right-side gap)
  - Added `overflow: hidden` to `.editor-container` (prevent overflow)
  - Changed `.editor-textarea` `overflow: auto` → `overflow: hidden` (let textarea handle its own scroll)
  - Added `overscroll-behavior: none` to body (prevent WKWebView rubber-banding)

### Phase 25: CodeEditor autoResize Fix ✅
- **Status:** complete
- **Files modified:** `examples/test-codeeditor.php`
- Added `$editor->autoResize($window, 0, 0, 20, 40)` — webview now follows window resize

### Phase 26: macOS Toast Notification (In Progress) ⏳
- **Status:** in_progress
- **Problem:** macOS 15 Sequoia silently drops all native notification APIs from non-bundled CLI processes (PHP via Homebrew)
- **Attempted approaches (all failed):**
  1. `NSUserNotificationCenter` — class exists but `defaultUserNotificationCenter` returns `nil` (Apple gutted it)
  2. `UNUserNotificationCenter` — crashes with `NSInternalInconsistencyException` (no bundle identifier)
  3. `CFUserNotificationDisplayNotice` — likely routes through UN internally; no visible result
  4. `osascript` via `system()` — exit 0 but no notification appears from PHP/FFI context
  5. `osascript` via `NSTask` — same, exit 0, no notification
  6. `osascript` via double-fork + setsid() — fully detached, still no notification
  7. ToastHelper.app bundle via `open` — minimal .app with Info.plist + UNUserNotificationCenter, still silent
- **Current approach:** In-app overlay NSWindow (borderless floating window styled as native toast, top-right, 4s auto-dismiss)

## Session: 2026-06-27

### Phase 27: SystemInfo Utility ✅
- **Status:** complete
- **Installed:** `utopia-php/system` v0.10.5
- **Created:** `src/System/SystemInfo.php` — wraps Utopia System with graceful macOS fallbacks
- **Created:** `examples/test-system-info.php` — demo CLI script
- **Note:** Utopia System has limited macOS support (CPU usage, memAvailable throw on Darwin). All handled with try/catch returning null.
- **Memory unit normalization:** Utopia returns different units per OS (Darwin=MB, Linux=kB). SystemInfo normalizes to bytes.

### Phase 28: ProcessUtil Utility ✅
- **Status:** complete
- **Installed:** `illuminate/process` v13.x-dev (17 dependencies including symfony/process)
- **Created:** `src/System/ProcessUtil.php` — wraps illuminate/process with convenient static + fluent API
- **Created:** `examples/test-process-util.php` — 8-tests demo covering run/capture/success/which/fluent/error/throw
- **Fixed:** Duplicate `run()` method (static + instance) → renamed instance method to `execute()`
1. Group::titled() requires 2 args — fixed 5 call sites
2. App::run() returns void — restructured with $mainWindow ref
3. Build::hbox() rejects Composite — added ->root()
4. Private const → public const in ToggleSwitch/StatusIndicator
5. Dialogs::msgBoxError() → ::error()
6. ToggleSwitch: `use Libui\StrokeParams` → `use Libui\Draw\StrokeParams`
7. FontDescriptor: `$font->family` → `$font->family()`
8. Bridge dylib rpath: rebuilt with -rpath flag
9. GC dangling-pointer: all-components.php rewritten, no inline temporaries
10. Namespace: `Libui\Widget\Button` → `Libui\Button`
11. CircleProgressBar: `Color` → `Brush::color()` in strokePath()
12. Bridge ARC: removed manual `[obj release]` calls
13. Bridge mouse/keyboard: NSWindow acceptMouseMovedEvents + makeKeyAndOrderFront
14. WebView eval queue: 300ms deferred flush via Ffi::timer()
15. CodeEditor focus: KeyableWindow subclass overrides canBecomeKeyWindow for borderless NSWindow
