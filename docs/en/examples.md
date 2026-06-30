# Examples

Run the examples from the project root:

```bash
php examples/all-components.php   # Full demo with 6 tabs showing all widgets
php examples/menu.php              # Declarative vs imperative menu APIs
php examples/webview.php           # WebView with sidebar, JS ↔ PHP bridge
php examples/tetris.php             # Full Tetris game using Area custom drawing
```

## all-components.php

Demonstrates every widget in this package across 6 tabs:

1. **Fields** — all input field types
2. **Custom** — ToggleSwitch, StatusIndicator, CircleProgressBar
3. **Dialogs** — MessageBox, DialogConfirm, DialogPrompt, Toast
4. **Pickers** — Color, Font, Date, Time pickers
5. **Table** — Tabular data with TableView
6. **WebView** — TreeView and CodeEditor

## tetris.php

A complete Tetris game implemented entirely with `Area` custom drawing — no external game engine or canvas needed. Demonstrates:

- **`Area` + `AreaDelegate`** — custom 2D rendering with `draw()`, keyboard handling with `key()`
- **`Loop::repeat()`** — gravity timer ticking the game board at increasing speeds
- **`DrawContext` builder** — cell rendering with 3D bevel effect, ghost piece preview, grid lines
- **Keyboard input** — arrow keys (via `ExtKey`) for movement, rotation, soft/hard drop
- **Game mechanics** — 7 tetrominoes, wall kicks, line clearing, score/level/lines tracking
- **Overlays** — pause screen, game over overlay drawn directly on the Area

```bash
php examples/tetris.php
```

Controls: ← → ↓ move, ↑ rotate, Space hard drop, R restart, Escape pause/resume.

## Test Files

Additional test scripts in `examples/` for individual features:

| Script | Feature |
|---|---|
| `test-fields.php` | Field component tests |
| `test-widgets.php` | Custom widget tests |
| `test-pickers.php` | Picker dialog tests |
| `test-circle-progress.php` | Circle progress bar |
| `test-treeview.php` | TreeView widget |
| `test-codeeditor.php` | CodeEditor widget |
| `test-tray.php` | System tray |
| `test-context-menu.php` | Context menu (area and standard) |
| `test-global-hotkey.php` | Global hotkey registration |
| `toast-test.php` | Toast notification |
| `test-system-info.php` | System information |
| `test-log.php` | Log viewer |
| `test-process-util.php` | Process utility |
| `test-svg.php` | SVG rendering |
| `test-debug-bridge.php` | Bridge debugging |
| `test-set-icon.php` | App icon setting |
| `tetris.php` | Full Tetris game — Area custom drawing, keyboard input, gravity timer, ghost piece, score system |
