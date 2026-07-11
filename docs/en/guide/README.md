# Introduction

**yangweijie/ui2** is a thin convenience layer over [`helgesverre/libui`](https://github.com/HelgeSverre/libui) — a native desktop GUI toolkit for PHP powered by `libui-ng` via FFI.

This package adds composite widgets, field helpers, picker dialogs, custom-drawn widgets, an embedded WebView engine, tree/file browser, code editor, circular progress bars, SVG rendering, a full chart component, a command-based rendering engine with theme system and widget renderer registry, a flexbox/grid layout engine, an event system, semantics/accessibility layer, and a Surface canvas widget that composes them all together.

## Project Structure

| Path | Purpose |
|------|---------|
| `src/` | Your code — `Yangweijie\Ui2\` namespace |
| `src/Composite.php` | Abstract base for multi-control widgets |
| `src/EmitsEvents.php` | Trait: `on(event, handler)` / `emit(event, data)` |
| `src/Fields/` | Label + input combos (TextField, NumberField, CheckboxField, etc.) |
| `src/Pickers/` | Modal picker dialogs (Color, Font, DatePicker, TimePicker) |
| `src/Dialogs/` | MessageBox, DialogConfirm, DialogPrompt |
| `src/Widgets/` | Custom-drawn widgets: ToggleSwitch, StatusIndicator, CircleProgressBar, Toast, TableView, **SvgView/SvgDelegate, Surface** (composable canvas), RendererButton, plus 15+ Surface-based Controls |
| `src/Layout/` | Flexbox layout (LayoutStyle, LayoutNode, FlexLayout) + Grid layout (GridTrack, GridStyle, GridLayout) + TabContainer, GroupSection |
| `src/Rendering/` | RenderCommand pipeline, DesignTokens theme system, WidgetRenderer registry + 10 built-in renderers |
| `src/Events/` | PointerEvent, KeyboardEvent, FocusManager — unified input model for Surface widgets |
| `src/Semantics/` | WidgetRole enum, SemanticsNode — accessibility layer |
| `src/Charts/` | Chart.js-style chart component (Line, Bar, Pie, Doughnut, Scatter) with animations, zoom, themes, hover tooltip — pure Area custom drawing |
| `src/WebView.php` | Embedded browser via borderless child window |
| `assets/` | HTML/JS assets for WebView-based widgets |
| `patches/` | Override files for upstream (mirrored into vendor/ on install) |
| `bridge/` | C/ObjC source for WebView child-window bridge |
| `bootstrap.php` | Auto-loaded via composer autoload — registers Collision error handler |
