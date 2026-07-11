# Architecture

## Composite Pattern

The core abstraction is `Composite` — an abstract base for widgets built from multiple controls. A `Composite` wraps one or more child controls behind a single `root()` method so the whole group can be added to containers (`Box`, `Form`, `Grid`) as if it were a single widget.

```php
abstract class Composite implements HasValue
{
    abstract public function root(): Control;
    public function value(): mixed { /* override in subclasses */ }
    public function setValue(mixed $value): static { /* override */ }
}
```

All container patches (`Box`, `Form`, `Grid`, `Group`, `Tab`) accept `Composite` children transparently — they call `$composite->root()` internally.

## EmitsEvents Trait

A lightweight event emitter trait. Drop it into any class to add `on(event, handler)` / `emit(event, data)`.

```php
class MyWidget extends Composite
{
    use EmitsEvents;

    public function doSomething(): void
    {
        $this->emit('change', $this->value());
    }
}

$widget->on('change', fn ($val) => print("Changed: {$val}"));
```

All Field composites use this trait and emit `'change'` when the input value changes.

## Rendering Engine (`src/Rendering/`)

Three subsystems that work together to provide a structured drawing pipeline.

### RenderCommand Pipeline

`RenderCommand` is a serialisable drawing instruction — instead of calling `DrawContext` methods directly, you build a command list and execute it in batch:

```php
use Yangweijie\Ui2\Rendering\RenderCommandList;

$cmd = new RenderCommandList();
$cmd->begin()
    ->addBoxShadow(offsetX: 2, offsetY: 2, blurRadius: 8, color: [0, 0, 0, 0.2])
    ->addFill(0x3B82F6)
    ->addRoundedRect(10, 10, 100, 50, 8)
    ->addTranslate(10, 10)
    ->addDrawString('Hello', 0, 0, $font, 0xFFFFFF);
// $cmd->execute($drawContext) — runs all commands in sequence
```

`CommandExecutor` consumes the command list on a `DrawContext`. `RenderCommandList` manages the ordered list with implicit new-command mode (`begin()` → `add*()` methods → `end()` returns `$this`).

`CircleProgressDelegate` was extracted from the `CircleProgressBar` widget into the `Rendering` namespace, providing an independent testable component.

### DesignTokens Theme System

`DesignTokens` is an **immutable** value object representing the full visual theme:

```php
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\ThemeKey;

$tokens = new DesignTokens();
$tokens = $tokens->with([ThemeKey::PRIMARY->value => 0x3B82F6]);
// $tokens is unchanged; with() returns a new instance
$text = $tokens->resolve(ThemeKey::TEXT);   // auto-computed contrast
$bg   = $tokens->resolve(ThemeKey::BG);     // from PRIMARY luminance
```

- `ThemeKey` enum defines all token keys (`PRIMARY`, `BG`, `TEXT`, `BORDER_RADIUS`, `FONT_SIZE`, plus component-specific: `SURFACE_*`, `CHART_*`, `CIRCLE_PROGRESS_*`, `TOGGLE_*`, etc.)
- Built-in derived colour helpers: `shade()` / `tint()` / `alpha()` / `isLight()` / `luminance()`
- `WidgetStyle` trait: provides `resolveColor($key, $overrides)` / `resolveStyle($key, $overrides)` for any widget class
- Extended tokens: `hoverColor` / `disabledColor` wash, `focusRing` (outer glow), `hairline` (1px border), `DARK` theme preset

### WidgetRenderer Registry

`WidgetRenderer` is an interface for drawing a control via the command pipeline:

```php
interface WidgetRenderer
{
    public function render(CommandList $cmds, array $bounds, array $state): void;
}
```

`RendererFactory` is a static registry:

```php
RendererFactory::register(new ButtonRenderer());
RendererFactory::register(new SliderRenderer());
$renderer = RendererFactory::make(ButtonRenderer::class);  // lookup by class
```

Built-in renderers (10 total):
- **Basic**: `ButtonRenderer`, `CardRenderer`
- **Form inputs**: `CheckboxRenderer`, `RadioRenderer`, `SliderRenderer`, `ProgressRenderer`
- **Text**: `TextFieldRenderer`, `SelectRenderer`

`RendererButton` is a **bridge widget** — it extends `Composite`, wraps a real libui `Button`, but draws its appearance via `ButtonRenderer` + `DesignTokens`. This lets you mix libui native controls with custom-drawn ones.

## Layout Engine (`src/Layout/`)

Two independent layout algorithms, both using immutable style objects.

### Flexbox Layout

```php
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\FlexLayout;

$style = (new LayoutStyle())->with([
    'width' => 100, 'height' => 50, 'margin' => 4, 'flexGrow' => 1,
]);
$node = new LayoutNode($style, $children);
$result = FlexLayout::layout($nodes, ['width' => 400, 'height' => 300]);
// Returns positioned children with computed x, y, width, height
```

- `LayoutStyle` — immutable style object (width/height/min/max/padding/margin/flexGrow/flexShrink/alignSelf). `with()` returns new instance
- `LayoutNode` — node tree (children/parent), recursive computation. `layout()` accepts position constraints, `measure()` returns subtree size. Dirty flag + cache
- `FlexLayout::layout($nodes, $constraints)` — classic flexbox algorithm: sizing → main-axis (flexGrow distributes remaining) → cross-axis (stretch/center/flex-start/flex-end). `flex-wrap` via explicit `<br>` nodes
- 16 tests covering: sizing, margins, flexGrow, wrap, nesting, cross-axis, min/max, overflow

### Grid Layout

```php
use Yangweijie\Ui2\Layout\GridStyle;
use Yangweijie\Ui2\Layout\GridLayout;

$gridStyle = (new GridStyle())->with([
    'columns' => ['1fr', '2fr', '1fr'],
    'rows' => ['auto', 'min-content'],
    'gap' => 8,
]);
$result = GridLayout::layout($nodes, $gridStyle, ['width' => 600, 'height' => 400]);
```

- `GridTrack` — row/column track definitions (fixed px / fr / min-content / max-content). `resolveTrack()` computes FR distribution
- `GridStyle` — immutable style (columns/rows/gap/placement). `with()` returns new instance
- `GridLayout::layout($nodes, $gridStyle, $constraints)` — track calculation → cell assignment → gap arrangement. 7 tests

## Event System (`src/Events/`)

Unified input model for pointer and keyboard events, designed for Surface-rendered widgets.

### PointerEvent

Wraps libui `AreaMouseEvent` into a standardised model:

```php
use Yangweijie\Ui2\Events\PointerEvent;

// Created internally by Surface from AreaMouseEvent:
$event = new PointerEvent(
    x: 42.0, y: 73.0,
    down: 1, held: 0, up: 0,
    modifiers: ['Shift'],
    clickCount: 1,
    timestamp: microtime(true),
);
```

### KeyboardEvent

Wraps libui `AreaKeyEvent`:

```php
use Yangweijie\Ui2\Events\KeyboardEvent;

$event = new KeyboardEvent(
    key: 'a',             // character (or ExtKey name)
    modifers: ['Ctrl'],    // held modifiers
    up: false,             // true = key release
);
```

### FocusManager

Tab-order navigation and focus tracking:

```php
use Yangweijie\Ui2\Events\FocusManager;

$fm = new FocusManager();
$fm->requestFocus('button1');
$fm->focusNext();    // tab to next focusable
$fm->blur();
$focused = $fm->getFocused();  // null or current focus ID
```

## Semantics / Accessibility (`src/Semantics/`)

### WidgetRole

Enum defining the semantic role of a widget:

```php
use Yangweijie\Ui2\Semantics\WidgetRole;

// Button, Checkbox, Radio, Slider, TextField, Label, Image, List,
// TreeGrid, Tab, ProgressBar, StatusBar
```

### SemanticsNode

Describes a widget's accessibility properties:

```php
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\Semantics\SemanticsNode;

$node = new SemanticsNode(
    role: WidgetRole::Button,
    label: 'Submit',
    value: null,
    enabled: true,
    checked: null,              // null = not checkable
    pressed: false,
    focused: false,
    selected: false,
    bounds: [0, 0, 100, 40],    // x, y, width, height
    children: [],
);
$flat = $node->flatten();      // returns flat list for screen reader consumption
```

## Surface Architecture

`Surface` is the **integration point** for all the above systems. It extends `Composite`, wraps a single libui `Area`, and orchestrates:

1. **Layout pass** — `FlexLayout` positions all child widgets
2. **Render pass** — `RendererFactory` resolves each child's `WidgetRenderer`, which uses the `RenderCommand` pipeline to produce draw commands
3. **Event dispatch** — `Area`'s `mouse()` / `key()` callbacks → `PointerEvent` / `KeyboardEvent` → routed to focused child

```
┌────────────────────────────────────────────────┐
│ Surface (Composite → Area + AreaDelegate)       │
│                                                  │
│  ├─ FlexLayout ◄── Layout children              │
│  ├─ RendererFactory ◄── resolve renderers       │
│  ├─ RenderCommandList ◄── batch draw commands   │
│  ├─ FocusManager ◄── keyboard focus             │
│  └─ SemanticsNode ◄── build accessibility tree  │
│                                                  │
│  draw(): FlexLayout→RendererFactory→RenderCmds   │
│  mouse(): AreaMouseEvent→PointerEvent→dispatch   │
│  key():   AreaKeyEvent→KeyboardEvent→FocusManager│
└────────────────────────────────────────────────┘
```

`Surface` can be placed in any libui container (`Box`, `Form`, `Grid`, `Tab`). It is **not** a child-window-based widget.

## Important: Composite GC Trap

Temporary `Composite` objects (e.g. `(new SeparatorLine())->root()`) get `__destruct()` called at statement end via PHP's GC, which calls `uiControlDestroy()` on the underlying C widget while libui still holds a reference. **Always store Composites in named persistent variables.**

If you see `uiControlVerifySetParent` errors, this is the cause.
