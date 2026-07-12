<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Control;
use Libui\Ffi;
use Libui\MultilineEntry;
use Libui\Draw\DrawContext;
use Libui\Draw\Matrix;
use Libui\Draw\Path;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Draw\StrokeParams;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\Events\FocusManager;
use Yangweijie\Ui2\Events\KeyboardEvent;
use Yangweijie\Ui2\Events\PointerEvent;
use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\CommandExecutor;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SearchFieldSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TabSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TableRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;

/**
 * A self-drawn canvas that lays out and renders a {@see LayoutNode} tree itself,
 * bypassing libui's Box/Group entirely, AND owns the full interaction layer:
 * pointer (hover / press / drag / click-count), keyboard (Tab + Shift+Tab focus
 * navigation, Enter / Space activation) and a focus manager that draws a focus
 * ring around the active widget.
 *
 * This is the GPUI-style "one canvas + own layout + self-drawn widgets + own
 * event routing" model: libui is used only for the top-level window and this one
 * Area — no Box, no Group, no title-as-width-anchor hacks.
 *
 * ```php
 * $surface = new Surface(
 *     LayoutNode::row(gap: 8, padding: 12)
 *         ->child(LayoutNode::leaf('save', new ButtonSpec('Save'), width: 100, height: 36))
 *         ->child(LayoutNode::leaf('cancel', new ButtonSpec('Cancel', 'outline'), width: 100, height: 36))
 * );
 * $surface->onClick('save', fn () => save());
 * $surface->onDoubleClick('save', fn () => saveAs());
 * $window->setChild(Build::stretchy($surface->root()));
 * ```
 */
class Surface extends Composite
{
    private Area $area;
    private SurfaceDelegate $delegate;
    public DesignTokens $tokens;
    private RendererRegistry $registry;
    private LayoutNode $root;
    private FocusManager $focus;

    /** Hidden native MultilineEntry used as an IME proxy for the focused TextArea. */
    private ?MultilineEntry $imeEntry = null;

    /** Layout node of the focused TextArea, used to position the IME popup. */
    private ?LayoutNode $imeProxyNode = null;

    /** Cached NSView pointer of the Surface's Area — used to restore focus after IME composition ends. */
    private ?\FFI\CData $areaNsViewPtr = null;

    /** Cached bridge FFI handle — used to check IME composition state in onChanged callback. */
    private ?\FFI $imeBridgeFfi = null;
    /** Flag indicating that the composition end has been processed (prevents re-entry) */
    private bool $imeCompositionProcessed = false;
    /** Optional IME proxy text value — cached before ime_unmark_text() to avoid stale reads */
    private ?string $imeCachedText = null;
    /** Guard against recursive onChanged from ime_unmark_text() */
    private bool $imeUnmarking = false;

    /** Optional modal overlay tree painted on top of (and capturing events from) the root. */
    private ?LayoutNode $overlay = null;

    /** Invoked when the overlay is dismissed via Escape. */
    private $overlayDismiss = null;

    /** @var array<string, callable():void> */
    private array $clickHandlers = [];

    /** @var array<string, callable():void> */
    private array $doubleClickHandlers = [];

    /** @var array<string, callable(float,float,float,float):void> */
    private array $dragHandlers = [];

    /** @var array<string, callable(string,bool):void> text-input handlers keyed by leaf id */
    private array $textHandlers = [];

    /** @var array<string, callable(float,float):void> scroll handlers (arrow keys) keyed by node id */
    private array $scrollHandlers = [];

    /** @var array<string, callable(string):void> caret handlers (arrow keys in text fields) keyed by node id */
    private array $caretHandlers = [];

    /** @var array<string, callable():void> drag-end handlers keyed by node id */
    private array $dragEndHandlers = [];

    /** Optional anchor rect [x,y,w,h] for an edge/popover overlay (null = center). */
    private ?array $overlayAnchor = null;

    /** Last known draw/event area size, for overlay positioning math. */
    private float $lastAreaW = 800.0;

    private float $lastAreaH = 600.0;

    public function __construct(
        LayoutNode $root,
        ?DesignTokens $tokens = null,
        ?RendererRegistry $registry = null,
    ) {
        $this->root = $root;
        $this->tokens = $tokens ?? new DesignTokens();
        $this->registry = $registry ?? RendererRegistry::default();
        $this->focus = new FocusManager();
        $this->focus->onChange(function (?string $old, ?string $new) {
            fwrite(STDERR, "[Surface] FocusManager onChange: old=" . ($old ?? 'null') . ", new=" . ($new ?? 'null') . "\n");
            $this->redraw();
            $this->handleImeFocus($old, $new);
        });

        $this->delegate = new SurfaceDelegate($this);
        // A plain (non-scrolling) Area. Like every other self-drawn widget in
        // this repo, the Surface is the stretchy child of a Box and receives its
        // on-screen size, reported as areaWidth/areaHeight in the draw callback.
        // Any internal scrolling (e.g. the catalogue ScrollView) is handled by
        // our own paint path (paintScrollContent), not by libui.
        $this->area = new Area($this->delegate);
        $this->refreshFocusables();
    }

    public function root(): Control
    {
        return $this->area;
    }

    public function rootLayout(): LayoutNode
    {
        return $this->root;
    }

    public function registry(): RendererRegistry
    {
        return $this->registry;
    }

    /** The focus manager driving Tab navigation + the drawn focus ring. */
    public function focus(): FocusManager
    {
        return $this->focus;
    }

    /** Re-collect the tab order from the current tree (call after mutating it). */
    public function refreshFocusables(): static
    {
        $this->focus->setTabOrder(LayoutNode::focusables($this->activeRoot()));

        return $this;
    }

    /**
     * Show or hide a modal overlay (a separate {@see LayoutNode} tree). While an
     * overlay is set, it is painted above the dimmed root and captures all
     * pointer / keyboard events, and the focus tab order switches to the
     * overlay's focusable leaves.
     *
     * Pass $anchor ([x,y,w,h]) to position the overlay card at an edge or near a
     * point (Drawer / Sheet / Popover); omit it for a centred Dialog.
     */
    public function setOverlay(?LayoutNode $overlay, ?array $anchor = null): static
    {
        $this->overlay = $overlay;
        $this->overlayAnchor = $anchor;
        $this->refreshFocusables();
        $this->redraw();

        return $this;
    }

    /** The currently shown overlay, or null. */
    public function overlay(): ?LayoutNode
    {
        return $this->overlay;
    }

    /** The overlay anchor rect [x,y,w,h], or null when centred. */
    public function overlayAnchor(): ?array
    {
        return $this->overlayAnchor;
    }

    /** Last known area width (from the most recent draw / pointer event). */
    public function lastAreaWidth(): float
    {
        return $this->lastAreaW;
    }

    /** Last known area height (from the most recent draw / pointer event). */
    public function lastAreaHeight(): float
    {
        return $this->lastAreaH;
    }

    /**
     * Record the on-screen area size from a draw / pointer pass. Surfaces are
     * written from {@see SurfaceDelegate}, which is a separate class and so
     * cannot touch the private fields directly — it must go through this setter.
     */
    public function setLastAreaSize(float $w, float $h): void
    {
        $this->lastAreaW = $w;
        $this->lastAreaH = $h;
    }

    /**
     * On-screen rect [x, y, w, h] of the node with the given id, in mouse/
     * viewport coordinates (scroll offsets of ancestor scroll views applied).
     * Used by popover / dropdown controls to anchor their overlay panel at the
     * trigger's visible position. Returns null when the id is unknown.
     *
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public function screenRectOf(string $id): ?array
    {
        $path = LayoutNode::pathTo($this->rootLayout(), $id);
        if ($path === null) {
            return null;
        }
        $node = $path[count($path) - 1];
        $x = $node->x;
        $y = $node->y;
        // Walk every ancestor and subtract its scroll offset so the rect ends up
        // in viewport space (what the mouse pointer actually sees).
        foreach ($path as $ancestor) {
            if ($ancestor === $node) {
                continue;
            }
            if ($ancestor->spec instanceof ScrollViewSpec) {
                $x -= $ancestor->scrollX;
                $y -= $ancestor->scrollY;
            }
        }

        return [$x, $y, $node->w, $node->h];
    }

    /** Register a callback fired when the overlay is dismissed via the Escape key. */
    public function onOverlayDismiss(callable $fn): static
    {
        $this->overlayDismiss = $fn;

        return $this;
    }

    /**
     * The tree that currently owns focus + events: the overlay when one is
     * shown, otherwise the root.
     */
    private function activeRoot(): LayoutNode
    {
        return $this->overlay ?? $this->root;
    }

    /** Check whether the IME proxy MultilineEntry is currently active. */
    public function imeProxyActive(): bool
    {
        return $this->imeEntry !== null;
    }

    /** Register a single-click handler for the node with the given id. */
    public function onClick(string $nodeId, callable $fn): static
    {
        $this->clickHandlers[$nodeId] = $fn;

        return $this;
    }

    /** Register a double-click handler for the node with the given id. */
    public function onDoubleClick(string $nodeId, callable $fn): static
    {
        $this->doubleClickHandlers[$nodeId] = $fn;

        return $this;
    }

    /** Register a drag-move handler for the node with the given id.
     *
     * The callback receives (x, y, w, h) where x/y are relative to the node's
     * top-left and clamped to the node's bounds, and w/h are the node's size.
     */
    public function onDrag(string $nodeId, callable $fn): static
    {
        $this->dragHandlers[$nodeId] = $fn;

        return $this;
    }

    /**
     * Register a text-input handler for the node with the given id. While that
     * node is focused, printable keystrokes and Backspace are forwarded here:
     * the callback receives ($char, $isBackspace) and is expected to mutate the
     * node's spec (append/delete) and repaint.
     */
    public function onText(string $nodeId, callable $fn): static
    {
        $this->textHandlers[$nodeId] = $fn;

        return $this;
    }

    public function textHandlerFor(string $id): ?callable
    {
        return $this->textHandlers[$id] ?? null;
    }

    /**
     * Register a scroll handler for the node with the given id. While that node
     * is focused, Arrow keys are forwarded here as (deltaX, deltaY) in pixels.
     */
    public function onScroll(string $nodeId, callable $fn): static
    {
        $this->scrollHandlers[$nodeId] = $fn;

        return $this;
    }

    public function scrollHandlerFor(string $id): ?callable
    {
        return $this->scrollHandlers[$id] ?? null;
    }

    /**
     * Register a caret-navigation handler for the node with the given id. While
     * that node is focused, Arrow keys are forwarded here as one of 'left',
     * 'right', 'up', 'down'. Used by the self-drawn textarea.
     */
    public function onCaret(string $nodeId, callable $fn): static
    {
        $this->caretHandlers[$nodeId] = $fn;

        return $this;
    }

    public function caretHandlerFor(string $id): ?callable
    {
        return $this->caretHandlers[$id] ?? null;
    }

    /** Register a drag-end handler, fired when a pressed node is released. */
    public function onDragEnd(string $nodeId, callable $fn): static
    {
        $this->dragEndHandlers[$nodeId] = $fn;

        return $this;
    }

    public function dragEndHandlerFor(string $id): ?callable
    {
        return $this->dragEndHandlers[$id] ?? null;
    }

    public function handlerFor(string $id): ?callable
    {
        return $this->clickHandlers[$id] ?? null;
    }

    public function doubleClickHandlerFor(string $id): ?callable
    {
        return $this->doubleClickHandlers[$id] ?? null;
    }

    public function dragHandlerFor(string $id): ?callable
    {
        return $this->dragHandlers[$id] ?? null;
    }

    /** Apply a theme override and repaint. */
    public function setTheme(array $overrides): static
    {
        $this->tokens = $this->tokens->applyTheme($overrides);
        $this->redraw();

        return $this;
    }

    /** Force a repaint (e.g. after mutating a node's spec). */
    public function redraw(): void
    {
        $this->delegate->redraw();
    }

    /**
     * Handle IME focus change: when focus moves to a TextAreaSpec node,
     * create a native MultilineEntry as an IME proxy. The OS IME
     * composition window appears at the MultilineEntry's position, and the
     * typed text is synced to the TextArea.
     *
     * libui's AreaKeyEvent->Key is a single `char` (1 byte), capturing only
     * ASCII (0-127). Multi-byte UTF-8 characters (Chinese, emoji) are lost.
     * The native MultilineEntry receives full Unicode input via the OS IME.
     *
     * @param string|null $old The node id that lost focus
     * @param string|null $new The node id that gained focus
     */
    private function handleImeFocus(?string $old, ?string $new): void
    {
        fwrite(STDERR, "[Surface] handleImeFocus: old=" . ($old ?? 'null') . ", new=" . ($new ?? 'null') . "\n");
        // Destroy any existing IME entry
        $this->detachImeEntry();

        if ($new === null) {
            return;
        }

        // Check if the newly focused node is a TextAreaSpec
        $node = LayoutNode::find($this->rootLayout(), $new);
        if ($node === null || !($node->spec instanceof TextAreaSpec)) {
            fwrite(STDERR, "[Surface] handleImeFocus: not a TextAreaSpec\n");
            return;
        }

        fwrite(STDERR, "[Surface] handleImeFocus: TextAreaSpec found, creating IME proxy\n");

        // Remember the focused TextArea's node for IME popup positioning
        $this->imeProxyNode = $node;

        $ffi = Ffi::get();

        // Create the IME proxy
        $this->imeEntry = new MultilineEntry();
        $this->imeEntry->setText($node->spec->value ?? '');

        // Get the parent container of the Surface's Area (should be a Box)
        $parentHandle = $ffi->uiControlParent($this->area->asControl());

        if ($parentHandle === null) {
            // No parent found — cannot position the IME entry. Log and skip.
            fwrite(STDERR, "[Surface] Warning: no parent container for IME entry\n");
            return;
        }

        // Cast uiControl* to uiBox* — uiBoxAppend requires the exact type.
        $parentBox = $ffi->cast('uiBox*', $parentHandle);

        // Add the MultilineEntry to the parent container using FFI
        // uiBoxAppend(parent, child, stretchy=0) — non-stretchy, minimal size
        try {
            $ffi->uiBoxAppend($parentBox, $this->imeEntry->asControl(), 0);
        } catch (\Error|\Throwable) {
            // Parent might not be a Box — try as a Window via uiWindowInsertChildAt
            fwrite(STDERR, "[Surface] Warning: parent is not a Box, cannot add IME entry\n");
            return;
        }

        // The MultilineEntry must be visible and receive keyboard focus
        // to get IME input. It's made 1x1 and transparent so the user
        // doesn't notice it. The IME popup will appear near the TextArea
        // because the Surface captures pointer events and forwards them.
        //
        // To make the IME popup appear at the right position, we need
        // uiControlFocus + uiControlMoveTo, which are not available in
        // this libui version. As a workaround, the entry is left at its
        // default position — the IME popup may appear in the wrong place,
        // but Chinese/emoji characters will still be captured and synced.
        $this->imeEntry->show();
        fwrite(STDERR, "[Surface] IME proxy shown\n");

        // Focus the IME proxy via the Cocoa bridge so the OS IME popup appears.
        // uiControlFocus doesn't exist in this libui version, so we use the
        // native bridge dylib to call becomeFirstResponder on the NSView.
        $this->focusImeEntry();
        fwrite(STDERR, "[Surface] IME proxy focused\n");

        // Sync text changes from the MultilineEntry to the TextArea
        $this->imeEntry->onChanged(function () use ($node): void {
            try {
                // Always read text from MultilineEntry — this is the simplest
                // and most reliable approach. The text may include pinyin prefix
                // during composition, but at least the TextArea will update.
                $text = $this->imeEntry->text();
                fwrite(STDERR, "[Surface] IME onChanged: text=\"" . $text . "\" imeBridgeFfi=" . ($this->imeBridgeFfi !== null ? 'yes' : 'no') . " entry=" . ($this->imeEntry !== null ? 'yes' : 'no') . " nsview=" . ($this->areaNsViewPtr !== null ? 'yes' : 'no') . " unmarking=" . ($this->imeUnmarking ? 'yes' : 'no') . "\n");

                // Try to unmark text if composition just ended (clears pinyin prefix).
                // This is best-effort — if it fails, we still update with raw text.
                fwrite(STDERR, "[Surface] IME onChanged: entering try block\n");
                if ($this->imeBridgeFfi !== null
                    && $this->imeEntry !== null
                    && $this->areaNsViewPtr !== null
                    && !$this->imeUnmarking) {
                    try {
                        fwrite(STDERR, "[Surface] IME onChanged: getting control\n");
                        $control = Ffi::control($this->imeEntry->handle());
                        fwrite(STDERR, "[Surface] IME onChanged: getting handle\n");
                        $ime_ns_view = Ffi::get()->uiControlHandle($control);
                        fwrite(STDERR, "[Surface] IME onChanged: casting\n");
                        $ime_ns_view_ptr = Ffi::get()->cast('void*', $ime_ns_view);
                        fwrite(STDERR, "[Surface] IME onChanged: ime_get_text_view\n");
                        $text_view_ptr = $this->imeBridgeFfi->ime_get_text_view($ime_ns_view_ptr);
                        fwrite(STDERR, "[Surface] IME onChanged: text_view_ptr=" . var_export($text_view_ptr, true) . "\n");
                        // Check if pointer is actually NULL (CData object but value=0).
                        // FFI NULL pointers are still CData objects, so !== null won't catch them.
                        $isNull = false;
                        try {
                            $isNull = (int) $text_view_ptr === 0;
                        } catch (\Throwable $_) {
                            $isNull = true;
                        }
                        fwrite(STDERR, "[Surface] IME onChanged: text_view_ptr_is_null=" . ($isNull ? 'yes' : 'no') . "\n");
                        if (!$isNull
                            && $this->imeBridgeFfi->ime_is_composing($text_view_ptr) === 0) {
                            // Composition ended — unmark BEFORE updating TextArea.
                            // This clears the marked text so the next onChanged
                            // reads clean text without pinyin prefix.
                            fwrite(STDERR, "[Surface] IME onChanged: calling unmark\n");
                            $this->imeUnmarking = true;
                            $this->imeBridgeFfi->ime_unmark_text($text_view_ptr);
                            $this->imeUnmarking = false;
                            fwrite(STDERR, "[Surface] IME onChanged: unmarked\n");
                        }
                    } catch (\Error|\Throwable $e) {
                        fwrite(STDERR, "[Surface] IME onChanged: unmark error: " . get_class($e) . " - " . $e->getMessage() . "\n");
                    }
                }
                fwrite(STDERR, "[Surface] IME onChanged: AFTER try block, entering cond check\n");

                // Only update TextArea if text is non-empty and changed.
                // Skip empty text to avoid overwriting correct value.
                fwrite(STDERR, "[Surface] IME onChanged: checking cond text=\"" . $text . "\" (" . strlen($text) . " bytes, " . mb_strlen($text) . " chars) spec-value=\"" . $node->spec->value . "\" (" . strlen($node->spec->value) . " bytes) eq=" . ($text === $node->spec->value ? 'YES' : 'NO') . " empty=" . ($text === '' ? 'YES' : 'NO') . " control=" . ($node->spec->control !== null ? 'yes' : 'no') . "\n");
                if ($text !== '' && $text !== $node->spec->value) {
                    fwrite(STDERR, "[Surface] IME onChanged: text=\"" . $text . "\" spec-value=\"" . $node->spec->value . "\" control=" . ($node->spec->control !== null ? 'yes' : 'no') . "\n");
                    // Update TextAreaControl's state (value + cursor + autoscroll + redraw).
                    // This keeps $this->value in sync with the spec, so syncSpec/autoscroll
                    // use correct data.
                    if ($node->spec->control !== null) {
                        $node->spec->control->setValue($text);
                        fwrite(STDERR, "[Surface] IME onChanged: via control->setValue, value=\"" . $node->spec->control->getValue() . "\"\n");
                    } else {
                        // Fallback: update spec directly (no control back-ref).
                        $node->spec = new TextAreaSpec(
                            value: $text,
                            placeholder: $node->spec->placeholder,
                            enabled: $node->spec->enabled,
                            focused: $node->spec->focused,
                            hovered: $node->spec->hovered,
                            radius: $node->spec->radius,
                            scrollY: $node->spec->scrollY,
                            cursor: mb_strlen($text),
                            lineHeight: $node->spec->lineHeight,
                            fontSize: $node->spec->fontSize,
                            control: $node->spec->control,
                        );
                        $this->redraw();
                    }
                    fwrite(STDERR, "[Surface] IME onChanged: final value=\"" . $node->spec->value . "\" cursor=" . (property_exists($node->spec, 'cursor') ? $node->spec->cursor : '?') . "\n");
                }
            } catch (\Error|\Throwable $e) {
                fwrite(STDERR, "[Surface] IME onChanged: EXCEPTION " . get_class($e) . " - " . $e->getMessage() . "\n");
            }
        });
    }

    /**
     * Load the IME bridge dylib and call becomeFirstResponder on the proxy's NSView.
     *
     * libui's uiControlFocus does not exist, so we must use a compiled C/Objective-C
     * bridge to call the Cocoa runtime directly. The bridge dylib lives at
     * bridge/ime_bridge.dylib (same location as webview_bridge.dylib).
     */
    private function focusImeEntry(): void
    {
        if ($this->imeEntry === null) {
            return;
        }

        try {
            // Get the NSView handle from the MultilineEntry via uiControlHandle
            $ffi_libui = Ffi::get();
            $control = Ffi::control($this->imeEntry->handle());
            $ns_view_int = $ffi_libui->uiControlHandle($control);
            $ns_view_ptr = $ffi_libui->cast('void*', $ns_view_int);
            fwrite(STDERR, "[Surface] focusImeEntry: ns_view_int=" . $ns_view_int . "\n");

            // Load the bridge dylib — try relative to bridge/ first, then /tmp
            $bridgePath = __DIR__ . '/../../bridge/ime_bridge.dylib';
            if (!\is_file($bridgePath)) {
                $bridgePath = '/tmp/ime_bridge.dylib';
                if (!\is_file($bridgePath)) {
                    fwrite(STDERR, "[Surface] Warning: ime_bridge.dylib not found, IME focus skipped\n");
                    return;
                }
            }

            $ffi_bridge = \FFI::cdef('
                int ime_make_first_responder(void* view);
                void ime_resign_first_responder(void* view);
                void ime_set_first_responder(void* window, void* view);
                void ime_set_view_frame(void* view, double x, double y, double w, double h);
                void* ime_get_key_window(void);
                int ime_is_composing(void* view);
                void ime_get_view_frame(void* view, double* x, double* y, double* w, double* h);
                void* ime_get_text_view(void* view);
                void ime_activate_and_focus_view(void* view);
            ', $bridgePath);

            // Store the bridge FFI handle for use in the onChanged callback.
            $this->imeBridgeFfi = $ffi_bridge;

            // Get the Surface Area's NSView handle — needed for both position calculation
            // and focus transfer.
            $area_control = Ffi::control($this->area->handle());
            $area_ns_view = $ffi_libui->uiControlHandle($area_control);
            $area_ns_view_ptr = $ffi_libui->cast('void*', $area_ns_view);

            // Position the proxy's NSView at the TextArea's layout rect so the
            // IME candidate popup appears near the actual input field instead
            // of at the bottom of the screen where libui placed the hidden Box child.
            //
            // The IME entry is a child of the parent Box, so its NSView frame is in
            // the parent Box's coordinate system. The TextArea's layout coordinates
            // are in the Surface's local coordinate system. We need to add the
            // Surface Area's NSView offset to convert to parent-relative coordinates.
            if ($this->imeProxyNode !== null) {
                $node = $this->imeProxyNode;

                // Get the Surface Area's NSView frame in the parent Box's coordinate system.
                $area_x = $ffi_libui->new('double');
                $area_y = $ffi_libui->new('double');
                $area_w = $ffi_libui->new('double');
                $area_h = $ffi_libui->new('double');

                $ffi_bridge->ime_get_view_frame(
                    $area_ns_view_ptr,
                    \FFI::addr($area_x),
                    \FFI::addr($area_y),
                    \FFI::addr($area_w),
                    \FFI::addr($area_h)
                );

                // Convert TextArea's Surface-local coordinates to viewport space.
                // rectFor() already subtracts scroll offsets of all ancestor ScrollViewSpec nodes.
                $rect = $this->screenRectOf($node->id);
                if ($rect === null) {
                    fwrite(STDERR, "[Surface] Warning: cannot get rect for IME node {$node->id}\n");
                    return;
                }
                [$rect_x, $rect_y, $rect_w, $rect_h] = $rect;

                // Add the Surface Area's NSView offset to get parent-relative coordinates.
                $ime_x = $rect_x + $area_x->cdata;
                $ime_y = $rect_y + $area_y->cdata;
                $ime_w = $rect_w;
                $ime_h = $rect_h;


                $ffi_bridge->ime_set_view_frame(
                    $ns_view_ptr,
                    $ime_x,
                    $ime_y,
                    $ime_w,
                    $ime_h
                );
            }

            // 1. Resign first responder from the Surface's Area NSView
            $this->areaNsViewPtr = $area_ns_view_ptr;
            $ffi_bridge->ime_resign_first_responder($area_ns_view_ptr);

            // 2. Activate the app, make the window key, and make the IME entry first responder.
            // This is critical for the IME popup to appear - the IME framework requires the
            // window to be properly key and the app to be active.
            $ffi_bridge->ime_activate_and_focus_view($ns_view_ptr);
            fwrite(STDERR, "[Surface] focusImeEntry: ime_activate_and_focus_view completed\n");
        } catch (\Error|\Throwable $e) {
            fwrite(STDERR, "[Surface] Warning: IME focus error: " . $e->getMessage() . "\n");
        }
    }

    /** Destroy the hidden IME entry and release its resources. */
    private function detachImeEntry(): void
    {
        if ($this->imeEntry !== null) {
            // Hide and destroy the widget. The onChanged callback is automatically
            // discarded when the widget is destroyed.
            $this->imeEntry->hide();
            $this->imeEntry->destroy();
            $this->imeEntry = null;
        }
        $this->imeProxyNode = null;
        $this->imeBridgeFfi = null;
        $this->areaNsViewPtr = null;
    }
}

/**
 * @internal Drives the Surface's Area: lays out + paints the node tree and routes
 * pointer / keyboard events to node handlers by hit-testing the computed rects.
 */
final class SurfaceDelegate extends AreaDelegate
{
    private ?string $pressedId = null;

    /** Held-button bitmask from the previous mouse event, for press/drag/release classification. */
    private ?int $prevHeld = null;

    /** Drag target resolved for the active press (may be an ancestor scroll view). */
    private ?string $dragId = null;

    /** True once a held-button move has occurred, so release can suppress the click. */
    private bool $dragged = false;

    public function __construct(private readonly Surface $surface)
    {
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $root = $this->surface->rootLayout();
        // areaWidth/areaHeight is the on-screen size libui allocated to this
        // (non-scrolling) Area; lay the whole tree out within it.
        $w = $params->areaWidth;
        $h = $params->areaHeight;
        $this->surface->setLastAreaSize($w, $h);
        FlexLayout::layout($root, 0, 0, $w, $h);
        $this->paint($ctx, $root);

        // Modal overlay: dim the (already painted) root, then paint the overlay
        // tree on top and capture its events. Anchored overlays (Drawer/Sheet/
        // Popover) are laid out inside their anchor rect instead of full-area.
        $overlay = $this->surface->overlay();
        if ($overlay !== null) {
            $anchor = $this->surface->overlayAnchor();
            if ($anchor !== null) {
                FlexLayout::layout($overlay, $anchor[0], $anchor[1], $anchor[2], $anchor[3]);
            } else {
                FlexLayout::layout($overlay, 0, 0, $w, $h);
            }
            $this->paintScrim($ctx, $w, $h);
            $this->paint($ctx, $overlay);
        }
    }

    /** Dim the whole area behind a modal overlay. */
    private function paintScrim(DrawContext $ctx, float $width, float $height): void
    {
        $ctx->fillRoundedRect(0, 0, $width, $height, 0, $this->surface->tokens->scrim());
    }

    /** Paint a node's own spec (if any) then its children, front-to-back. */
    private function paint(DrawContext $ctx, LayoutNode $node): void
    {
        if ($node->spec !== null) {
            $renderer = $this->surface->registry()->get($node->spec->type());
            if ($renderer !== null) {
                $spec = $this->withState($node);
                $ctx->withSave(function (DrawContext $ctx) use ($renderer, $spec, $node): void {
                    $ctx->transform((new Matrix())->translate($node->x, $node->y));
                    $list = $renderer->render($spec, $this->surface->tokens, $node->w, $node->h);
                    (new CommandExecutor())->execute($ctx, $list);
                    $list->free();
                });

                $this->paintFeedback($ctx, $node);
            }
        }

        if ($node->spec instanceof ScrollViewSpec) {
            $this->paintScrollContent($ctx, $node);
        } else {
            foreach ($node->children as $child) {
                $this->paint($ctx, $child);
            }
        }
    }

    /**
     * Paint a scroll container's children clipped to the viewport rect and
     * translated by the negative scroll offset, so they scroll underneath the
     * chrome drawn by {@see ScrollViewRenderer}.
     */
    private function paintScrollContent(DrawContext $ctx, LayoutNode $node): void
    {
        $inset = 1.0;
        $path = (new Path())->addRectangle(
            $node->x + $inset,
            $node->y + $inset,
            $node->w - 2 * $inset,
            $node->h - 2 * $inset,
        )->end();

        $ctx->withSave(function (DrawContext $ctx) use ($node, $path): void {
            $ctx->clip($path);
            $ctx->transform((new Matrix())->translate(-$node->scrollX, -$node->scrollY));
            foreach ($node->children as $child) {
                $this->paint($ctx, $child);
            }
        });
    }

    /**
     * Focus ring, drawn on top of the widget.
     *
     * Hover / disabled feedback is now owned entirely by each renderer: every
     * self-drawn widget consumes the token-driven wash inside its own
     * shapeCommands() (see {@see \Yangweijie\Ui2\Rendering\WidgetRenderer\TokenWash}),
     * so the Surface only adds the keyboard focus ring on top. That keeps the
     * wash consistent across the whole catalogue (ButtonRenderer parity) and
     * avoids double-drawing.
     */
    private function paintFeedback(DrawContext $ctx, LayoutNode $node): void
    {
        // Prefer the widget's own corner radius for the ring; fall back to a
        // sensible default for widgets (e.g. slider) without a radius field.
        $radius = 8.0;
        if ($node->spec !== null && property_exists($node->spec, 'radius')) {
            $radius = $node->spec->radius;
        }

        if ($node->id !== null && $this->surface->focus()->isFocused($node->id)) {
            $pad = $this->surface->tokens->focusRingGap();
            $ctx->strokeRoundedRect(
                $node->x - $pad, $node->y - $pad,
                $node->w + $pad * 2, $node->h + $pad * 2,
                $radius + $pad,
                $this->surface->tokens->focusRing(),
                StrokeParams::solid($this->surface->tokens->focusRingWidth()),
            );
        }
    }

    /** Fold the node's pressed/hovered state into an immutable spec copy. */
    private function withState(LayoutNode $node): WidgetSpec
    {
        $spec = $node->spec;

        if ($spec instanceof ButtonSpec) {
            return new ButtonSpec(
                label: $spec->label,
                variant: $spec->variant,
                enabled: $spec->enabled,
                pressed: $node->pressed,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof SliderSpec) {
            return new SliderSpec(
                value: $spec->value,
                enabled: $spec->enabled,
                pressed: $node->pressed,
                hovered: $node->hovered,
            );
        }

        if ($spec instanceof CheckboxSpec) {
            return new CheckboxSpec(
                checked: $spec->checked,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                label: $spec->label,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof RadioSpec) {
            return new RadioSpec(
                selected: $spec->selected,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                label: $spec->label,
            );
        }

        if ($spec instanceof ProgressSpec) {
            return new ProgressSpec(
                value: $spec->value,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof SelectSpec) {
            return new SelectSpec(
                value: $spec->value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof TextFieldSpec) {
            return new TextFieldSpec(
                value: $spec->value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof SearchFieldSpec) {
            return new SearchFieldSpec(
                value: $spec->value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
                showClear: $spec->value !== '',
            );
        }

        if ($spec instanceof CardSpec) {
            return new CardSpec(
                bordered: $spec->bordered,
                hovered: $node->hovered,
                radius: $spec->radius,
                elevation: $spec->elevation,
            );
        }

        if ($spec instanceof ListRowSpec) {
            return new ListRowSpec(
                label: $spec->label,
                subtitle: $spec->subtitle,
                selected: $spec->selected,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof TableRowSpec) {
            return new TableRowSpec(
                cells: $spec->cells,
                widths: $spec->widths,
                header: $spec->header,
                selected: $spec->selected,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof TabSpec) {
            return new TabSpec(
                label: $spec->label,
                active: $spec->active,
                enabled: $spec->enabled,
                hovered: $node->hovered,
                radius: $spec->radius,
            );
        }

        if ($spec instanceof ScrollViewSpec) {
            return new ScrollViewSpec(
                enabled: $spec->enabled,
                scrollX: $node->scrollX,
                scrollY: $node->scrollY,
                contentWidth: $spec->contentWidth,
                contentHeight: $spec->contentHeight,
                viewportWidth: $spec->viewportWidth,
                viewportHeight: $spec->viewportHeight,
                radius: $spec->radius,
                vertical: $spec->vertical,
                horizontal: $spec->horizontal,
            );
        }

        if ($spec instanceof TextAreaSpec) {
            $control = $spec->control;
            // If TextAreaControl owns this spec, pull the live value from it
            // (TextAreaControl::syncSpec() keeps $value in sync with edits).
            $value = $control !== null ? $control->getValue() : $spec->value;
            fwrite(STDERR, "[Surface] withState: TextAreaSpec value=\"" . $value . "\" spec-value=\"" . $spec->value . "\" control=" . ($control !== null ? 'yes' : 'no') . "\n");
            return new TextAreaSpec(
                value: $value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
                scrollY: $spec->scrollY,
                cursor: $control !== null ? $control->getCursor() : $spec->cursor,
                lineHeight: $spec->lineHeight,
                fontSize: $spec->fontSize,
                control: $control,
            );
        }

        return $spec;
    }

    /** Call a drag handler with the node's rect-relative coordinates. */
    private function callDragHandler(LayoutNode $root, string $id, float $x, float $y): void
    {
        $handler = $this->surface->dragHandlerFor($id);
        if ($handler === null) {
            return;
        }
        $node = LayoutNode::find($root, $id);
        if ($node === null) {
            return;
        }
        // The event $x/$y are SCREEN coordinates, but $node->x/$node->y are
        // LAYOUT coordinates (un-scrolled). When the target is nested inside a
        // scrolled ancestor, the two differ by the ancestor's scroll offset.
        // Subtract that accumulated offset so the handler sees correct local
        // coordinates — otherwise nested scrollbars / sliders get a frozen
        // delta (e.g. ry clamps to 0) and cannot be dragged.
        [$accX, $accY] = $this->accumulatedScroll($root, $id);
        $rx = max(0.0, min($x - $node->x + $accX, $node->w));
        $ry = max(0.0, min($y - $node->y + $accY, $node->h));
        $handler($rx, $ry, $node->w, $node->h);
    }

    /**
     * Sum of scroll offsets of every ScrollViewSpec ancestor between $root and
     * the node with $id (exclusive of the node itself). Used to convert SCREEN
     * pointer coordinates into a nested scroll viewport's LOCAL coordinates.
     *
     * @return array{0:float,1:float}  [accumulatedScrollX, accumulatedScrollY]
     */
    private function accumulatedScroll(LayoutNode $root, string $id, float $accX = 0.0, float $accY = 0.0): array
    {
        if ($root->id === $id) {
            return [$accX, $accY];
        }
        $childAccX = $accX + ($root->spec instanceof ScrollViewSpec ? $root->scrollX : 0.0);
        $childAccY = $accY + ($root->spec instanceof ScrollViewSpec ? $root->scrollY : 0.0);
        foreach ($root->children as $child) {
            $found = $this->accumulatedScroll($child, $id, $childAccX, $childAccY);
            if ($found !== null) {
                return $found;
            }
        }

        return [$accX, $accY];
    }

    /**
     * Decide which node owns the drag gesture for a press on $hit:
     *  - a widget that already handles its own dragging (slider, scrollbar
     *    thumb…) keeps the gesture so we never hijack it;
     *  - otherwise, a press inside a scroll viewport pans that viewport's
     *    content (this is what makes "grab the content and drag" work).
     */
    private function resolveDragTarget(LayoutNode $root, ?string $hit): ?string
    {
        if ($hit === null) {
            return null;
        }
        if ($this->surface->dragHandlerFor($hit) !== null) {
            return $hit;
        }
        $owner = $this->nearestScrollViewAncestor($root, $hit);
        if ($owner !== null && $owner->id !== null) {
            return $owner->id;
        }

        return $hit;
    }

    /** Nearest ancestor ScrollViewSpec of $id (excluding $id itself), or null. */
    private function nearestScrollViewAncestor(LayoutNode $root, string $id): ?LayoutNode
    {
        $path = LayoutNode::pathTo($root, $id);
        if ($path === null) {
            return null;
        }
        for ($i = count($path) - 2; $i >= 0; $i--) {
            if ($path[$i]->spec instanceof ScrollViewSpec) {
                return $path[$i];
            }
        }

        return null;
    }

    public function mouse(AreaMouseEvent $event): void
    {
        // For a scrolling Area the mouse event only carries the content size
        // in areaWidth/areaHeight, which is not useful for layout. Use the
        // viewport size recorded by the last draw pass instead.
        $w = $this->surface->lastAreaWidth();
        $h = $this->surface->lastAreaHeight();

        // When a modal overlay is up, route every pointer event to it only.
        $overlay = $this->surface->overlay();
        if ($overlay !== null) {
            $anchor = $this->surface->overlayAnchor();
            if ($anchor !== null) {
                FlexLayout::layout($overlay, $anchor[0], $anchor[1], $anchor[2], $anchor[3]);
            } else {
                FlexLayout::layout($overlay, 0, 0, $w, $h);
            }
            $root = $overlay;
        } else {
            $root = $this->surface->rootLayout();
            FlexLayout::layout($root, 0, 0, $w, $h);
        }
        $hit = LayoutNode::findAt($root, $event->x, $event->y);

        // Robust, backend-agnostic button handling. Different libui builds
        // disagree on whether the press frame carries `down`, `held`, or
        // neither, and some report a held-button MOVE with both bits clear
        // (mis-classified as HOVER). We therefore:
        //   * begin a gesture on a fresh press: `down != 0`, or a rising edge
        //     of `held` (prevHeld 0 -> held 1);
        //   * keep the gesture STICKY once started, so move frames with no
        //     button bits still drive the drag (this is what makes scrollbar
        //     thumb / slider / body-pan dragging work on those builds);
        //   * end it only on a real release: `up != 0`, or `held` falling
        //     (prevHeld 1 -> held 0).
        $prevHeld = $this->prevHeld ?? 0;
        // Release is primarily a real `up` event (libui sets Up on mouse-up
        // reliably). We also accept a held 1->0 fall ONLY once a drag has
        // actually moved: some builds report held-button MOVE frames with the
        // held bit cleared, so a held 1->0 on the first move frame must NOT be
        // mistaken for a release (that would end the gesture before the delta
        // is applied and make the scrollbar impossible to drag).
        $isRelease = $event->up !== 0
            || ($this->dragged && $prevHeld === 1 && $event->held === 0);
        $beginPress = ! $isRelease
            && ($event->down !== 0 || ($event->held !== 0 && $prevHeld === 0));
        $this->prevHeld = $event->held;

        $this->dbgMouse($event, $hit, $beginPress, $isRelease, $this->dragId !== null);

        if ($beginPress) {
            // Begin (or restart) a drag/pan gesture on a fresh press edge.
            $this->pressedId = $hit;
            // Resolve which node owns the gesture: a widget that handles its
            // own dragging (slider, scrollbar thumb…) keeps it; otherwise a
            // press inside a scroll viewport pans its content.
            $this->dragId = $this->resolveDragTarget($root, $hit);
            $this->dragged = false;
            $this->setPressed($root, $hit);
            if ($this->dragId !== null) {
                $this->callDragHandler($root, $this->dragId, $event->x, $event->y);
            }
            $this->redraw();

            return;
        }

        if ($this->dragId !== null) {
            if ($isRelease) {
                // End the gesture; suppress the click if it actually moved.
                $clicked = ! $this->dragged
                    && $this->pressedId !== null
                    && $this->pressedId === $hit;
                $draggedId = $this->dragId;
                $this->pressedId = null;
                $this->dragId = null;
                $this->dragged = false;
                $this->setPressed($root, null);
                $this->redraw();

                // End any in-progress drag (e.g. a scrollbar thumb grab / body pan).
                if ($draggedId !== null) {
                    $end = $this->surface->dragEndHandlerFor($draggedId);
                    if ($end !== null) {
                        $end();
                    }
                }

                if ($clicked && $hit !== null) {
                    // Click-to-focus: keyboard events (typing, arrows) now target
                    // this node. Standard for text fields and native-feeling for all.
                    $this->surface->focus()->focus($hit);

                    $handler = $this->surface->handlerFor($hit);
                    if ($handler !== null) {
                        $handler();
                    }
                    if ($event->count === 2) {
                        $dch = $this->surface->doubleClickHandlerFor($hit);
                        if ($dch !== null) {
                            $dch();
                        }
                    }
                }

                return;
            }

            // Continue an in-progress gesture. This also runs for move frames
            // that libui reports with both `down` and `held` clear — because the
            // gesture is sticky, the drag keeps tracking the pointer.
            $this->dragged = true;
            if ($this->dragId !== null) {
                $this->callDragHandler($root, $this->dragId, $event->x, $event->y);
            }
            $this->setPressed($root, $this->pressedId);
            $this->redraw();

            return;
        }

        if ($isRelease) {
            // A release with no active gesture: nothing to do.
            return;
        }

        // Hover.
        if ($this->setHovered($root, $hit)) {
            $this->redraw();
        }
    }

    /** Env-gated debug trace of raw mouse events (UI2_DEBUG_MOUSE=1). */
    private function dbgMouse(AreaMouseEvent $event, ?string $hit, bool $beginPress, bool $isRelease, bool $dragging): void
    {
        if (getenv('UI2_DEBUG_MOUSE') !== '1') {
            return;
        }
        $line = sprintf(
            "[MOUSE] x=%.1f y=%.1f down=%d up=%d count=%d held=%d hit=%s begin=%d release=%d dragging=%d dragId=%s pressedId=%s\n",
            $event->x, $event->y, $event->down, $event->up, $event->count, $event->held,
            $hit ?? 'null', $beginPress ? 1 : 0, $isRelease ? 1 : 0, $dragging ? 1 : 0,
            $this->dragId ?? 'null', $this->pressedId ?? 'null'
        );
        fwrite(STDERR, $line);
        @file_put_contents('/tmp/ui2_mouse.log', $line, FILE_APPEND);
    }

    /** Pointer left/entered the area. libui sets $left=true when it leaves. */
    public function mouseCrossed(bool $left): void
    {
        if ($left) {
            // Forget any held-button state so a press that ended outside the
            // window does not leave a stuck "button down" classification.
            $this->prevHeld = 0;
            $this->pressedId = null;
            $this->dragId = null;
            $this->dragged = false;
            $root = $this->surface->rootLayout();
            if ($this->setHovered($root, null)) {
                $this->redraw();
            }
        }
    }

    /** Keyboard: Tab / Shift+Tab navigation, Enter / Space activation, Esc dismiss. */
    public function key(AreaKeyEvent $event): bool
    {
        $k = KeyboardEvent::fromKey($event);

        // Escape dismisses a modal overlay (if a dismiss handler is wired).
        if ($k->isEscape() && $this->surface->overlay() !== null) {
            if ($this->surface->overlayDismiss !== null) {
                ($this->surface->overlayDismiss)();
            }

            return true;
        }

        if ($k->isShiftTab()) {
            $this->surface->focus()->focusPrev();

            return true;
        }

        if ($k->isTab()) {
            $this->surface->focus()->focusNext();

            return true;
        }

        // Text input: printable characters + Backspace go to the focused field.
        // Skip when the IME proxy (MultilineEntry) is active — it handles
        // multi-byte UTF-8 (Chinese/emoji) that AreaKeyEvent::Key cannot capture.
        $focused = $this->surface->focus()->current();
        if ($focused !== null) {
            $textHandler = $this->surface->textHandlerFor($focused);
            if ($textHandler !== null && ($k->isPrintable() || $k->isBackspace() || $k->isEnter())) {
                // If the IME proxy is active, skip text input here.
                // The MultilineEntry will receive keyboard events independently.
                if ($this->surface->imeProxyActive()) {
                    return false;
                }
                $textHandler($k->isBackspace() ? '' : $k->char, $k->isBackspace());

                return true;
            }
        }

        // Arrow keys: scroll a focused scroll viewport, or move the caret in a textarea.
        if ($focused !== null
            && ($k->isArrowUp() || $k->isArrowDown() || $k->isArrowLeft() || $k->isArrowRight())) {
            $node = $this->nodeById($focused);
            if ($node !== null && $node->spec instanceof ScrollViewSpec) {
                $step = 24.0;
                $dx = $k->isArrowRight() ? $step : ($k->isArrowLeft() ? -$step : 0.0);
                $dy = $k->isArrowDown() ? $step : ($k->isArrowUp() ? -$step : 0.0);
                $scroll = $this->surface->scrollHandlerFor($focused);
                if ($scroll !== null) {
                    $scroll($dx, $dy);

                    return true;
                }
            }
            if ($node !== null && $node->spec instanceof TextAreaSpec) {
                $dir = $k->isArrowLeft() ? 'left'
                    : ($k->isArrowRight() ? 'right'
                    : ($k->isArrowUp() ? 'up' : 'down'));
                $caret = $this->surface->caretHandlerFor($focused);
                if ($caret !== null) {
                    $caret($dir);

                    return true;
                }
            }
        }

        if (($k->isEnter() || $k->isSpace()) && $focused !== null) {
            $handler = $this->surface->handlerFor($focused);
            if ($handler !== null) {
                $handler();

                return true;
            }
        }

        return false;
    }

    /** Topmost node id whose rect contains (x, y), or null. */
    private function hitTest(LayoutNode $node, float $x, float $y): ?string
    {
        return LayoutNode::findAt($node, $x, $y);
    }

    /** Resolve a node by id, searching the active tree (root, or the overlay). */
    private function nodeById(string $id): ?LayoutNode
    {
        $found = LayoutNode::find($this->surface->rootLayout(), $id);
        if ($found !== null) {
            return $found;
        }

        $overlay = $this->surface->overlay();

        return $overlay === null ? null : LayoutNode::find($overlay, $id);
    }

    /** Set the pressed flag on the node whose id matches, clear it elsewhere. */
    private function setPressed(LayoutNode $node, ?string $id): void
    {
        if ($node->id !== null) {
            $node->pressed = ($node->id === $id);
        }
        foreach ($node->children as $child) {
            $this->setPressed($child, $id);
        }
    }

    /** Set hovered on the node matching $id, clear it on every other node. */
    private function setHovered(LayoutNode $node, ?string $id): bool
    {
        $changed = false;
        if ($node->id !== null && $node->hovered !== ($node->id === $id)) {
            $node->hovered = ($node->id === $id);
            $changed = true;
        }
        foreach ($node->children as $child) {
            if ($this->setHovered($child, $id)) {
                $changed = true;
            }
        }

        return $changed;
    }
}
