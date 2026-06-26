# Task Plan: Composite GUI Components

## Goal
Build a set of reusable Composite-based GUI components in `src/Fields/`, plus patches, dialogs, and custom widgets. Complete implementation of the 5-phase gap analysis derived from `software—arch.md`.

## Current Phase
Phase 26: macOS Toast Notification — In Progress ⏳

## Phases

### Phase 0-12: Core Components + Demo ✅
- All complete (see progress.md for details)

### Phase 13: WebView Port ✅
- [x] Bridge compiled with rpath, mouse/keyboard events
- [x] CodeEditor keyboard input fix

### Phase 14: Dialog/Picker Centering ✅
- [x] centeredOn(), SeparatorLine __destruct, 6 picker/dialog files

### Phase 15: 4 New Widgets ✅
- [x] CircleProgressBar, Toast, TreeView, CodeEditor

### Phase 16: all-components.php 6-Tab Demo ✅
- [x] Window 800×600, layout fixes, GC rewrite

### Phase 17: Test Coverage ✅
- [x] 47 → 120 tests, 11 files

### Phase 18: Toast Error Handling ✅
- [x] $lastError + lastError() static methods

### Phase 19: WebView Bridge Fixes ✅
- [x] ARC, rpath, mouse/keyboard events

### Phase 20: WebView Eval Queue ✅
- [x] setHtml() queues evals, flushes 300ms later via Ffi::timer()
- [x] test-debug-bridge.php passes all 6 steps

### Phase 21: TreeView PHP ↔ WebView Communication ✅
- [x] All sub-tasks complete (see progress.md)
- **Status:** complete

### Phase 22: CodeEditor Keyboard Focus Fix ✅
- **Status:** complete

### Phase 23: TreeView Button Event Fixes ✅
- [x] getSelectedPath() tracks path in PHP (no eval round-trip)
- [x] bindNodeClick/Toggle use positional args (not JSON.stringify)
- [x] Remove HTML __treeNodeClick/__treeNodeToggle no-ops
- [x] Fix `:scope > .tree-toggle` → `.tree-row > .tree-toggle`
- [x] Expand/collapse buttons context-aware (use selected path)
- **Status:** complete

### Phase 24: CodeEditor CSS Fixes ✅
- [x] Right-side gap: .code-area background, overflow fixes, overscroll-behavior
- **Status:** complete

### Phase 25: CodeEditor autoResize Fix ✅
- [x] Added autoResize() call in test-codeeditor.php
- **Status:** complete

### Phase 26: macOS Toast Notification ⏳
- [x] Diagnosed all 7 failed approaches (NSUserNotification/UNUserNotification/CFUserNotification/osascript/ToastHelper)
- [~] Current: in-app overlay NSWindow (borderless floating toast)
- **Status:** in_progress

### Phase 27: SystemInfo Utility ✅
- [x] Install utopia-php/system v0.10.5
- [x] Create src/System/SystemInfo.php with graceful macOS fallbacks
- [x] Create examples/test-system-info.php
- [x] Handle memory unit normalization (Darwin=MB, Linux=kB → bytes)
- **Status:** complete

### Phase 28: ProcessUtil Utility ✅
- [x] Install illuminate/process v13.x-dev
- [x] Create src/System/ProcessUtil.php with static + fluent API
- [x] Create examples/test-process-util.php (8 tests)
- [x] Fix duplicate run() method → renamed instance method to execute()
- **Status:** complete

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| bridge dylib: @rpath/PebView.dylib not found | 1 | Rebuilt with -rpath flag |
| uiControlVerifySetParent: control already has parent | 1 | GC: no inline temporaries |
| Class "Libui\Widget\Button" not found | 1 | `Libui\Button` |
| strokePath(): Argument #1 must be Brush | 1 | `Brush::color()` |
| Bridge ARC: [obj release] unavailable | 1 | Removed manual release calls |
| WebView keyboard input not received | 1 | NSWindow makeKeyAndOrderFront + acceptsMouseMovedEvents |
| webview_eval() silently fails after setHtml() | 1 | Queue evals, flush 300ms via Ffi::timer() |
| TreeView bind() creates functions too late | 1 | Define no-op bridge functions in HTML template |
| getSelectedPath() always returns null | 1 | Track path in PHP via nodeClick glue |
| req parameter is JSON array, not object | 1 | Use positional args binding |
| HTML no-op prevents onBind() fallback | 1 | Remove no-ops from HTML |
| `:scope > .tree-toggle` returns null | 1 | Use `.tree-row > .tree-toggle` |
| NSUserNotificationCenter.defaultCenter nil on macOS 15 | 1 | In-app overlay NSWindow |
| UNUserNotificationCenter crashes: no bundle ID | 1 | In-app overlay NSWindow (can't fix bundle for CLI PHP) |
| osascript NSTask/system() silent from FFI context | 2 | Use in-app overlay NSWindow |
| CFUserNotificationDisplayNotice silent on macOS 15 | 1 | Use in-app overlay NSWindow |
| ToastHelper.app via `open` silent | 1 | Use in-app overlay NSWindow |
