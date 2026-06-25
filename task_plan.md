# Task Plan: Composite GUI Components

## Goal
Build a set of reusable Composite-based GUI components in `src/Fields/`, plus patches, dialogs, and custom widgets. Complete implementation of the 5-phase gap analysis derived from `software—arch.md`.

## Current Phase
Phase 21 ✅ — All planned phases complete

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
- [x] WebView eval queue mechanism (pendingEval + flushPendingEval + 300ms timer)
- [x] setHtml() override re-injects PebView init script
- [x] createInitScript() builds full PebView Webview bridge JS
- [x] rebindHandlers() re-registers after page load
- [x] tree-view.html: __onNodeClick/__onNodeToggle defined as functions (not null)
- [x] bind() uses setTimeout(200ms) defer — fixes timing race with init script
- [x] getSelectedPath() reads tracked PHP property (no eval round-trip)
- [x] Toast $lastError error handling
- [x] Bridge ARC cleanup + mouse/keyboard events
- [x] bridge/README.md -rpath documentation
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
