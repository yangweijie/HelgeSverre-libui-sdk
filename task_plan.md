# Task Plan: Composite GUI Components

## Goal
Build a set of reusable Composite-based GUI components in `src/Fields/`, plus patches, dialogs, and custom widgets. Complete implementation of the 5-phase gap analysis derived from `software—arch.md`.

## Current Phase
Phase 16 — Demo Running (in progress)

## Phases

... (Phase 0-12 unchanged)...

### Phase 13: WebView Port (PebView Bridge)
- [x] composer.json: Add kingbes/pebview dependency
- [x] bridge/: Copy/macOS/Linux/Windows platform bridge C sources + compiled dylib
- [x] bridge/README.md: Build instructions
- [x] src/WebView.php: WebView class wrapping bridge + PebView FFI (navigate/setHtml/eval/bind/return/autoResize/cleanupOnClose/reposition/destroy)
- [x] examples/webview.php: Demo with sidebar, JS-PHP bridge, resize handling
- [x] Fix bridge dylib rpath: rebuild PebView.dylib from source, recompile bridge with -rpath flag
- [x] .gitignore: Ignore compiled bridge binaries
- **Status:** complete

### Phase 14: Dialog/Picker Centering on Parent
- [x] patches/Window.php: Add centeredOn(Window $parent) method
- [x] src/Fields/SeparatorLine.php: Add __destruct calling $this->separator->destroy()
- [x] src/Pickers/*.php: 4 pickers use centeredOn() after show()
- [x] src/Dialogs/DialogConfirm.php, DialogPrompt.php: Use centeredOn()
- **Status:** complete

### Phase 15: 4 New Widgets (CircleProgressBar, Toast, TreeView, CodeEditor)
- [x] src/Widgets/CircleProgressBar.php: Area-based ring progress bar (fillArc/strokeArc, setProgress/setColor/setThickness)
- [x] src/Widgets/Toast.php: Native OS toast via PebView Toast.dylib FFI (static show())
- [x] src/Widgets/TreeView.php: WebView-based tree/file browser with JS-PHP bridge (setData/expandNode/collapseNode/onNodeClick/onNodeToggle)
- [x] assets/tree-view.html: Collapsible tree HTML with icons, selection, toggle
- [x] src/Widgets/CodeEditor.php: WebView-based code editor with highlight.js (setCode/getCode/setLanguage/onChange)
- [x] assets/code-editor.html: CodeMirror-style editor with line numbers, toolbar, 17 language support
- **Status:** complete

### Phase 16: all-components.php 6-Tab Demo
- [x] Rewrite to 6 tabs: Fields, Custom, Dialogs, Pickers, Table, WebView
- [x] CircleProgressBar in Custom tab with +/-/Reset controls
- [x] Toast button in Dialogs tab
- [x] TreeView launch button in WebView tab
- [x] CodeEditor launch button in WebView tab
- [x] Fix GC/dangling-pointer bug: temporary Composite objects destroyed mid-expression. Rewrote all variables to named persistent storage. No more inline IIFEs.
- [x] Fix `use Libui\Widget\Button` → `use Libui\Button` (upstream has no `Widget\` sub-namespace)
- [ ] Fix CircleProgressBar `strokePath()` → `Brush::color()` (needs Brush not Color) — **pending test run**
- **Status:** in progress (1 known runtime fix remaining, awaiting verification)

## Errors Encounterded (additions)
| Error | Attempt | Resolution |
|-------|---------|------------|
| bridge dylib: @rpath/PebView.dylib not found | 1 | ran pebview macos.sh to build PebView.dylib from source; recompiled bridge with -rpath |
| uiControlVerifySetParent: control already has parent | 1 | GC collecting temporary Composite objects mid-expression. Rewrote all-components.php: no more inline temporaries |
| Class "Libui\Widget\Button" not found | 1 | Wrong namespace: `Libui\Widget\Button` → `Libui\Button` |
| strokePath(): Argument #1 must be Brush, Color given | 1 | Wrap Color with `Brush::color($color)` |
