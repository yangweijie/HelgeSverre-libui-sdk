<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Control;
use Libui\Ffi;
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
use Yangweijie\Ui2\WebView;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WebViewSpec;
use Yangweijie\Ui2\Semantics\SemanticProvider;
use Yangweijie\Ui2\Semantics\SemanticsNode;

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
class Surface extends Composite implements SemanticProvider
{
    private Area $area;
    private SurfaceDelegate $delegate;
    public DesignTokens $tokens;
    private RendererRegistry $registry;
    private LayoutNode $root;
    private FocusManager $focus;

    /** IME text overlay: a native editable text widget placed over the field's
     * screen rect so IME / CJK composition is handled by the OS. The overlay is
     * a transparent NSTextView (macOS, ime_bridge.m), a Win32 EDIT control
     * (Windows, ime_bridge_win.c), or a GTK entry/view (Linux, ime_bridge_linux.c)
     * — all exposed through the same FFI cdef. Created when an IME-capable field
     * is focused, destroyed when focus leaves.
     */
    private ?\FFI $imeBridgeFfi = null;

    /** @var \FFI|null Cached bridge FFI handle — parsed once, reused across detach/reattach. */
    private $imeBridgeCdef = null;

    /** @var \FFI\CData|null IME notify callback — must be retained or GC kills it */
    private ?\FFI\CData $imeNotifyCallback = null;

    /** @var callable|null IME tabFn — must be retained or GC kills it */
    private $imeTabCallback = null;

    /** @var callable|null IME notifyFn — must be retained or GC kills it */
    private $imeNotifyFn = null;

    /** @var callable|null IME tabFn — must be retained or GC kills it */
    private $imeTabFn = null;

    /** Latest text reported by the IME NSTextView (mirrors what the overlay shows). */
    private string $imeComposingText = '';

    /** Id of the node the IME NSTextView overlay is currently attached to (for scroll-follow). */
    private ?string $imeNodeId = null;


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

    /** @var list<callable(float,float):void> area-resize handlers (areaWidth, areaHeight) */
    private array $resizeHandlers = [];

    /** Optional anchor rect [x,y,w,h] for an edge/popover overlay (null = center). */
    private ?array $overlayAnchor = null;

    /**
     * Live WebView child windows keyed by LayoutNode id, one per visible
     * {@see WebViewSpec} leaf. Like the IME overlay, each is a real native
     * subview of the Area's view, glued to its node's on-screen rect every
     * frame by {@see syncWebViewOverlays()}.
     *
     * @var array<string, WebView>
     */
    private array $webviewOverlays = [];

    /**
     * Content signature (url/html + debug) per overlay id, used to detect
     * spec changes that require re-navigating / re-setting HTML.
     *
     * @var array<string, string>
     */
    private array $webviewSig = [];

    /**
     * Node ids awaiting WebView creation. Creation is deferred to a timer tick
     * (outside the Area draw callback) so we never allocate a Cocoa WKWebView
     * mid-draw; {@see flushWebViewPending()} performs it.
     *
     * @var array<string, WebViewSpec>
     */
    private array $webviewPending = [];

    /** Whether a one-shot timer to flush $webviewPending is already scheduled. */
    private bool $webviewTimerScheduled = false;

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

    /** The accessibility/automation tree for this self-drawn surface. */
    public function semantics(): ?SemanticsNode
    {
        return SemanticsNode::fromLayout($this->rootLayout());
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
     * Register a callback fired when the Area's on-screen size changes (and on
     * the first draw, so the caller learns the real area size instead of the
     * default placeholder). Receives (areaWidth, areaHeight) in pixels.
     */
    public function onResize(callable $fn): static
    {
        $this->resizeHandlers[] = $fn;

        return $this;
    }

    /** @internal Fired by SurfaceDelegate::draw on the first paint + on every resize. */
    public function fireResize(float $width, float $height): void
    {
        foreach ($this->resizeHandlers as $fn) {
            $fn($width, $height);
        }
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
     * Best-effort teardown of any embedded WebView overlays. The live browser
     * views are native subviews of the Area and are not tracked by libui, so we
     * must free them explicitly. Prefer calling
     * {@see destroyWebViewOverlays()} before the Window closes; this only runs
     * if the Surface is garbage-collected first.
     */
    public function __destruct()
    {
        try {
            $this->destroyWebViewOverlays();
        } catch (\Throwable) {
            // The native view/parent may already be gone during shutdown.
        }
    }

    /**
     * Handle IME focus change: when focus moves to a TextAreaSpec node,
     * create a native NSTextView inside the Area's NSScrollView as the IME
     * first responder. The OS IME popup appears at the TextArea's position,
     * and text changes are synced back to the TextArea via NSTextViewDidChangeNotification.
     *
     * libui's AreaKeyEvent->Key is a single `char` (1 byte), capturing only
     * ASCII (0-127). Multi-byte UTF-8 characters (Chinese, emoji) are lost.
     * The NSTextView receives full Unicode input via the OS IME.
     *
     * @param string|null $old The node id that lost focus
     * @param string|null $new The node id that gained focus
     */
    private function isImeCapableSpec(\Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec $spec): bool
    {
        return $spec instanceof TextAreaSpec
            || $spec instanceof TextFieldSpec
            || $spec instanceof SearchFieldSpec;
    }

    private function handleImeFocus(?string $old, ?string $new): void
    {
        $this->imeDbg("[Surface] handleImeFocus: old=" . ($old ?? 'null') . ", new=" . ($new ?? 'null') . "\n");

        // If focus is leaving the current IME-capable field, detach the text view.
        if ($old !== null) {
            $oldNode = LayoutNode::find($this->rootLayout(), $old);
            if ($oldNode !== null && $this->isImeCapableSpec($oldNode->spec)) {
                $this->imeDbg("[Surface] handleImeFocus: detaching IME text view\n");
                $this->detachImeTextview();
            }
        }

        if ($new === null) {
            return;
        }

        // Check if the newly focused node is an IME-capable text field
        // (TextArea / TextField / SearchField).
        $node = LayoutNode::find($this->rootLayout(), $new);
        if ($node === null || !$this->isImeCapableSpec($node->spec)) {
            $this->imeDbg("[Surface] handleImeFocus: not an IME-capable field\n");
            return;
        }

        $this->imeDbg("[Surface] handleImeFocus: " . get_class($node->spec) . " found, creating IME text view\n");

        // Remember which node owns the overlay so we can reposition it on scroll.
        $this->imeNodeId = $node->id;

        // Calculate the inner content rect for the IME NSTextView.
        // Multi-line TextArea uses PAD=8 (TextAreaRenderer) and is top-aligned.
        // Single-line fields align the overlay with their own text insets and
        // are vertically centered (TextField x=8; SearchField x=26, leaving room
        // for the magnifier + clear button). $vcenter tells the bridge to center
        // the NSTextView text so it matches the renderer's vertical centering.
        $inner = $this->imeInnerRect($node);
        if ($inner === null) {
            $this->imeDbg("[Surface] Warning: cannot get rect for {$node->id}\n");
            return;
        }
        [$innerX, $innerY, $innerW, $innerH, $vcenter] = $inner;

        // Get the Area NSView (which is an NSScrollView) via uiControlHandle
        $areaNsViewInt = Ffi::get()->uiControlHandle(Ffi::control($this->area->handle()));
        $areaNsViewPtr = Ffi::get()->cast('void*', $areaNsViewInt);

        // Load the platform-specific bridge library (same FFI cdef on all OSes).
        $bridgePath = self::imeBridgePath();
        if ($bridgePath === null) {
            $this->imeDbg("[Surface] Warning: ime_bridge not available on this platform, IME skipped\n");
            return;
        }

        // Parse the bridge cdef ONCE per Surface instance and reuse it. Re-parsing
        // the C header on every focus change (the old behaviour) was a measurable
        // source of focus latency.
        if ($this->imeBridgeCdef === null) {
            $this->imeBridgeCdef = \FFI::cdef('
                void ime_create_textview(void* area_ns_view, double x, double y, double w, double h, int vcenter, double font_size, const char* initial_text);
                void ime_destroy_textview(void);
                void ime_set_notify_callback(void (*callback)(const char*, int));
                void ime_clear_notify_callback(void);
                void ime_set_tab_callback(void (*callback)(int));
                void ime_clear_tab_callback(void);
                void* ime_get_textview(void);
                int ime_has_textview(void);
                void ime_set_text(const char* text);
                int ime_get_caret_position(void);
                void ime_set_caret_position(int pos);
                int ime_is_composing(void);
                int ime_make_textview_first_responder(void);
                void ime_clear_textview_first_responder(void);
                void ime_set_view_frame(void* view, double x, double y, double w, double h);
            ', $bridgePath);
        }

        $this->imeBridgeFfi = $this->imeBridgeCdef;
        $ffi_bridge = $this->imeBridgeFfi;

        // Get the current TextArea value and cursor
        $initialText = $node->spec->control !== null
            ? $node->spec->control->getValue()
            : $node->spec->value ?? '';
        $initialCursor = $node->spec->control !== null
            ? $node->spec->control->getCursor()
            : (property_exists($node->spec, 'cursor') ? $node->spec->cursor : 0);

        // Create the NSTextView in the bridge. Font size MUST match the renderer's
        // single-line field font (min(h*0.5, 14)) so the live overlay text is the
        // same size as the drawn text — otherwise the input looks "bigger".
        $fontSize = min($innerH * 0.5, 14.0);
        $ffi_bridge->ime_create_textview(
            $areaNsViewPtr,
            $innerX, $innerY, $innerW, $innerH,
            $vcenter,
            $fontSize,
            $initialText
        );

        // Reposition immediately using the (now laid-out) rect. Draw() also
        // repositions every frame, so even if the first focus computed a stale
        // rect the overlay snaps to the correct spot on the next paint.
        $this->repositionImeOverlay();

        // Make the NSTextView the first responder so it receives keyboard input.
        $ffi_bridge->ime_make_textview_first_responder();

        // Register the text change callback from C back to PHP
        $surface = $this;
        $controlRef = $node->spec->control;
        $this->imeDbg("[Surface] handleImeFocus: controlRef=" . ($controlRef !== null ? ('yes#' . spl_object_id($controlRef)) : 'null') . " initialText=\"" . $initialText . "\" node-id=" . ($node->id ?? 'null') . "\n");
        $notifyFn = function (string $text, int $caret) use ($surface, $node, $controlRef): void {
            $surface->imeDbg("[Surface] IME notifyFn called: text=\"" . $text . "\" (len=" . mb_strlen($text) . ") caret=" . $caret . " controlRef=" . ($controlRef !== null ? ('yes#' . spl_object_id($controlRef)) : 'null') . "\n");
            // Track the live overlay text so the renderer can hide the placeholder
            // the instant the user types (even mid-composition, before the control
            // value syncs) and restore it only when the field is cleared.
            $this->imeComposingText = $text;
            try {
                if ($controlRef !== null) {
                    $oldValue = $controlRef->getValue();
                    $surface->imeDbg("[Surface] IME notifyFn: controlRef->getValue()=\"{$oldValue}\" (len=" . mb_strlen($oldValue) . ")\n");
                    $surface->imeDbg("[Surface] IME notifyFn: calling setValue(\"" . $text . "\")\n");
                    $controlRef->setValue($text);
                    $surface->imeDbg("[Surface] IME notifyFn: after setValue(), controlRef->getValue()=\"" . $controlRef->getValue() . "\" (len=" . mb_strlen($controlRef->getValue()) . ")\n");
                    $controlRef->setCursor($caret);
                } else {
                    // No control (e.g. a raw TextFieldSpec leaf): commit the
                    // composed text directly into the node's spec so it persists
                    // after the IME overlay is detached.
                    $surface->imeDbg("[Surface] IME notifyFn: controlRef null, updating node spec directly\n");
                    $spec = $node->spec;
                    if ($spec instanceof TextFieldSpec) {
                        $node->spec = new TextFieldSpec(
                            value: $text,
                            placeholder: $spec->placeholder,
                            enabled: $spec->enabled,
                            focused: $spec->focused,
                            hovered: $spec->hovered,
                            radius: $spec->radius,
                        );
                    } elseif ($spec instanceof SearchFieldSpec) {
                        $node->spec = new SearchFieldSpec(
                            value: $text,
                            placeholder: $spec->placeholder,
                            enabled: $spec->enabled,
                            focused: $spec->focused,
                            hovered: $spec->hovered,
                            radius: $spec->radius,
                            showClear: $text !== '',
                        );
                    }
                }
                $surface->redraw();
            } catch (\Error|\Throwable $e) {
                $surface->imeDbg("[Surface] IME text change error: " . $e->getMessage() . "\n");
            }
        };
        // PHP FFI auto-converts a Closure into a C function pointer when the
        // cdef parameter is declared as a function-pointer type (see ime_bridge.m /
        // ime_bridge_win.c / ime_bridge_linux.c).
        // Retain the closure in $this->imeNotifyFn so the GC doesn't free it.
        $this->imeNotifyFn = $notifyFn;
        $ffi_bridge->ime_set_notify_callback($notifyFn);

        // Register the Tab/Shift+Tab callback for focus navigation.
        $this->imeTabFn = function (int $isShiftTab) use ($surface): void {
            if ($isShiftTab) {
                $surface->focus()->focusPrev();
            } else {
                $surface->focus()->focusNext();
            }
            $surface->redraw();
        };
        // Same closure-as-function-pointer pattern as the notify callback.
        $this->imeTabCallback = $this->imeTabFn;
        $ffi_bridge->ime_set_tab_callback($this->imeTabFn);

        // Set the initial caret position (suppresses notify)
        $ffi_bridge->ime_set_caret_position($initialCursor);
    }

    /** Destroy the IME NSTextView and clear the bridge FFI reference. */
    private function detachImeTextview(): void
    {
        $ffi = $this->imeBridgeFfi;
        if ($ffi === null) {
            return;
        }

        // Destroy the overlay view FIRST — this is the critical step that prevents
        // a ghost overlay (and the overlap it causes). A hang or throw in the
        // callback / first-responder cleanup below must NEVER block this, so it
        // runs in its own guarded block before anything else.
        try {
            $before = $ffi->ime_has_textview();
            $this->imeDbg("[Surface] detachImeTextview: BEFORE has_textview=" . var_export($before, true) . "\n");
            $ffi->ime_destroy_textview();
            $after = $ffi->ime_has_textview();
            $this->imeDbg("[Surface] detachImeTextview: AFTER has_textview=" . var_export($after, true) . "\n");
        } catch (\Error|\Throwable $e) {
            $this->imeDbg("[Surface] Warning: IME destroy error: " . $e->getMessage() . "\n");
        }

        // Best-effort cleanup of callbacks. Each is isolated so a failure in one
        // cannot prevent the others (or re-raise). Note: we deliberately do NOT
        // call ime_clear_textview_first_responder here — removing the view via
        // removeFromSuperview already resigns it, and calling it separately can
        // re-enter the focus machinery and block/hang the detach.
        foreach (['ime_clear_notify_callback', 'ime_clear_tab_callback'] as $clearFn) {
            try {
                $ffi->{$clearFn}();
            } catch (\Error|\Throwable $e) {
                $this->imeDbg("[Surface] Warning: IME {$clearFn} error: " . $e->getMessage() . "\n");
            }
        }

        $this->imeBridgeFfi = null;
        $this->imeNotifyCallback = null;
        $this->imeNotifyFn = null;
        $this->imeTabCallback = null;
        $this->imeTabFn = null;
        $this->imeComposingText = '';
        $this->imeNodeId = null;
    }

    /**
     * Inner content rect (in viewport coords) for the IME NSTextView overlay of
     * the given field node, plus a vcenter flag. Returns null when the node rect
     * can't be resolved. Shared by focus-attach and scroll-reposition so the
     * overlay always lands exactly on the rendered field.
     *
     * @return array{0:float,1:float,2:float,3:float,4:int}|null
     */
    private function imeInnerRect(LayoutNode $node): ?array
    {
        $rect = $this->screenRectOf($node->id);
        if ($rect === null) {
            return null;
        }
        [$tx, $ty, $tw, $th] = $rect;

        if ($node->spec instanceof TextAreaSpec) {
            $pad = 8.0;
            return [$tx + $pad, $ty + $pad, max(1.0, $tw - 2 * $pad), max(1.0, $th - 2 * $pad), 0];
        }
        if ($node->spec instanceof SearchFieldSpec) {
            return [$tx + 26.0, $ty, max(1.0, $tw - 26.0 - 30.0), $th, 1];
        }
        // TextFieldSpec (default): centered single-line field.
        return [$tx + 8.0, $ty, max(1.0, $tw - 16.0), $th, 1];
    }

    /**
     * Keep the live IME NSTextView overlay glued to its field while the surface
     * scrolls. The overlay is a real NSView child of the (non-scrolling) Area
     * view, so the Surface's fake scroll (translated DrawContext) does not move
     * it — without this it would stay fixed and appear as a floating ghost.
     */
    public function repositionImeOverlay(): void
    {
        if ($this->imeBridgeFfi === null || $this->imeNodeId === null) {
            return;
        }
        if ($this->imeBridgeFfi->ime_has_textview() !== 1) {
            return;
        }
        $node = LayoutNode::find($this->rootLayout(), $this->imeNodeId);
        if ($node === null) {
            return;
        }
        $inner = $this->imeInnerRect($node);
        if ($inner === null) {
            return;
        }
        [$x, $y, $w, $h] = $inner;
        $tv = $this->imeBridgeFfi->ime_get_textview();
        if ($tv === null) {
            return;
        }
        // The overlay is positioned in Area-view (screen) coords, which is exactly
        // what screenRectOf returns, so it tracks the field through the scroll.
        $this->imeBridgeFfi->ime_set_view_frame($tv, $x, $y, $w, $h);
    }

    /** Whether an IME NSTextView is currently mounted (overlay active). */
    public function isImeTextviewActive(): bool
    {
        return $this->imeBridgeFfi !== null
            && $this->imeBridgeFfi->ime_has_textview() === 1;
    }

    /** Latest text reported by the IME NSTextView (mirrors the overlay). */
    public function getImeComposingText(): string
    {
        return $this->imeComposingText;
    }

    /** Env-gated debug trace for IME internals (UI2_DEBUG_IME=1). */
    public function imeDbg(string $msg): void
    {
        if (getenv('UI2_DEBUG_IME') === '1') {
            fwrite(STDERR, $msg);
        }
    }

    /** Caret position reported by the IME NSTextView, or 0 when inactive. */
    public function getImeCaretPosition(): int
    {
        if ($this->imeBridgeFfi === null) {
            return 0;
        }
        return $this->imeBridgeFfi->ime_get_caret_position();
    }

    /**
     * Called when any scroll container scrolls. The IME NSTextView overlay is a
     * real NSView child of the (non-scrolling) Area view, so the Surface's fake
     * scroll does not move it. Instead of blurring the field (which detaches the
     * overlay and, under the layer-backed Area, can leave a fixed ghost that
     * never follows the scrolled field), we reposition the overlay to stay glued
     * to its field. The field keeps focus and the committed text is preserved.
     */
    public function onScrollContainerScrolled(string $scrollViewportId): void
    {
        $this->repositionImeOverlay();
    }

    // ────── Embedded WebView overlays (WebViewSpec) ──────

    /**
     * Keep one live WebView child window glued to each visible
     * {@see WebViewSpec} leaf, creating / loading / destroying as the tree
     * changes. Called every frame from {@see SurfaceDelegate::draw} after the
     * layout is established, so rects are always current.
     *
     * Creation of the native WebView is deferred to a timer tick (see
     * {@see flushWebViewPending()}) to avoid allocating a Cocoa WKWebView
     * inside the Area's draw callback.
     */
    public function syncWebViewOverlays(): void
    {
        try {
            $seen = [];

            $this->collectWebViewNodes($this->activeRoot(), $seen);

            // Drop overlays whose node disappeared from the tree.
            foreach (array_keys($this->webviewOverlays) as $id) {
                if (!isset($seen[$id])) {
                    $this->destroyWebView($id);
                }
            }
        } catch (\Throwable $e) {
            // Never let an overlay bookkeeping error escape into the Area draw
            // trampoline — that would crash the process with no message.
            \fwrite(\STDERR, "[Surface] syncWebViewOverlays error (ignored): {$e->getMessage()}\n");
        }
    }

    /**
     * Walk the tree; for each WebViewSpec leaf, reposition its existing overlay
     * (and reload on content change) or queue it for deferred creation.
     */
    private function collectWebViewNodes(LayoutNode $node, array &$seen): void
    {
        if ($node->spec instanceof WebViewSpec && $node->id !== null) {
            $id = $node->id;
            $seen[$id] = true;

            $rect = $this->screenRectOf($id);
            if ($rect === null) {
                return;
            }
            [$x, $y, $w, $h] = $rect;
            $sig = $this->webviewSigOf($node->spec);

            if (isset($this->webviewOverlays[$id])) {
                $this->webviewOverlays[$id]->reposition(
                    (int) $x,
                    (int) $y,
                    \max(1, (int) $w),
                    \max(1, (int) $h),
                );
                if (($this->webviewSig[$id] ?? null) !== $sig) {
                    $this->applyWebViewContent($this->webviewOverlays[$id], $node->spec);
                    $this->webviewSig[$id] = $sig;
                }
            } else {
                $this->webviewPending[$id] = $node->spec;
                $this->scheduleWebViewFlush();
            }

            return; // a webview leaf has no paintable children
        }

        foreach ($node->children as $child) {
            $this->collectWebViewNodes($child, $seen);
        }
    }

    /**
     * Create the queued WebView overlays outside the draw callback. Runs on a
     * libui timer tick (main thread, no active DrawContext), so allocating the
     * native browser view is safe.
     */
    private function flushWebViewPending(): void
    {
        if ($this->webviewPending === []) {
            return;
        }

        try {
            $areaHandle = $this->areaHandle();
        } catch (\Throwable $e) {
            \fwrite(\STDERR, "[Surface] Cannot resolve Area handle for WebView overlays: {$e->getMessage()}\n");
            return;
        }

        foreach (array_keys($this->webviewPending) as $id) {
            $spec = $this->webviewPending[$id];
            unset($this->webviewPending[$id]);

            $rect = $this->screenRectOf($id);
            if ($rect === null) {
                continue;
            }
            [$x, $y, $w, $h] = $rect;

            try {
                $wv = WebView::createOnHandle(
                    $areaHandle,
                    (int) $x,
                    (int) $y,
                    \max(1, (int) $w),
                    \max(1, (int) $h),
                    $spec->debug,
                );
                $this->applyWebViewContent($wv, $spec);
                $this->webviewOverlays[$id] = $wv;
                $this->webviewSig[$id] = $this->webviewSigOf($spec);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[Surface] WebView overlay '{$id}' creation failed: {$e->getMessage()}\n");
            }
        }
    }

    /**
     * Schedule a one-shot timer to flush {@see $webviewPending}, unless one is
     * already queued.
     */
    private function scheduleWebViewFlush(): void
    {
        if ($this->webviewTimerScheduled) {
            return;
        }
        $this->webviewTimerScheduled = true;

        $surface = $this;
        \Libui\Ffi::timer(16, function () use ($surface): bool {
            $surface->flushWebViewPending();
            $surface->webviewTimerScheduled = false;

            return false; // one-shot: stop after flushing
        });
    }

    /** Navigate / set HTML / bind JS functions on a freshly created overlay. */
    private function applyWebViewContent(WebView $wv, WebViewSpec $spec): void
    {
        if ($spec->url !== null) {
            $wv->navigate($spec->url);
        } elseif ($spec->html !== null) {
            $wv->setHtml($spec->html);
        }

        foreach ($spec->binds as $name => $handler) {
            $wv->bind($name, $handler);
        }
    }

    /** Stable signature of a spec's content (detects navigation changes). */
    private function webviewSigOf(WebViewSpec $spec): string
    {
        return ($spec->url !== null ? 'u:' . $spec->url : 'h:' . ($spec->html ?? ''))
            . '|' . ($spec->debug ? '1' : '0');
    }

    /** Native handle of the Area's view — the parent for WebView overlays. */
    private function areaHandle(): int
    {
        return \Libui\Ffi::get()->uiControlHandle(\Libui\Ffi::control($this->area->handle()));
    }

    /**
     * Return the live {@see WebView} overlay for a node id, or null when the
     * node isn't a WebViewSpec or its overlay hasn't been created yet. Use this
     * to drive advanced behaviour (eval / return / extra binds).
     */
    public function webviewOf(string $id): ?WebView
    {
        return $this->webviewOverlays[$id] ?? null;
    }

    /** Destroy a single WebView overlay (idempotent). */
    private function destroyWebView(string $id): void
    {
        try {
            $this->webviewOverlays[$id]?->destroy();
        } catch (\Throwable) {
            // best-effort — the native view may already be gone
        }
        unset($this->webviewOverlays[$id], $this->webviewSig[$id], $this->webviewPending[$id]);
    }

    /**
     * Destroy every embedded WebView overlay. Call this before the parent
     * Window / FFI is torn down to free the native browser views deterministically.
     */
    public function destroyWebViewOverlays(): void
    {
        foreach (array_keys($this->webviewOverlays) as $id) {
            $this->destroyWebView($id);
        }
        $this->webviewPending = [];
    }

    /**
     * Resolve the platform-specific IME bridge library path.
     *
     * Returns the first existing candidate, or null when no build is present.
     * The same FFI cdef is used regardless of platform, so callers do not need
     * to care which OS they are on.
     *
     * @return string|null
     */
    private static function imeBridgePath(): ?string
    {
        $base = \dirname(__DIR__, 2) . '/bridge';
        $candidates = match (\PHP_OS_FAMILY) {
            'Darwin'  => [$base . '/ime_bridge.dylib', '/tmp/ime_bridge.dylib'],
            'Linux'   => [$base . '/ime_bridge.so'],
            'Windows' => [$base . '/ime_bridge.dll'],
            default   => [],
        };
        foreach ($candidates as $path) {
            if (\is_file($path)) {
                return $path;
            }
        }
        return null;
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
        // Fire onResize on the first draw and on every size change so the host
        // can rebuild content sized to the real area (libui's Area is the source
        // of truth — initial size guesses are off by the OS window margin).
        if ($w !== $this->surface->lastAreaWidth() || $h !== $this->surface->lastAreaHeight()) {
            $this->surface->fireResize($w, $h);
        }
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

        // Keep the live IME NSTextView overlay glued to its field. Doing this on
        // every frame means the overlay snaps to the correct rect as soon as the
        // layout is established — this fixes the "first focus shows nothing" case
        // (the rect wasn't ready when focus fired) and tracks the field through
        // any scroll/redraw without an explicit scroll handler.
        $this->surface->repositionImeOverlay();

        // Same job for any embedded WebViewSpec leaves: glue a real WebView
        // child window to each node's on-screen rect (creating/loading it as
        // needed). Mirrors the IME overlay lifecycle.
        $this->surface->syncWebViewOverlays();
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
            // While the IME overlay is active on this focused field, drive the
            // renderer from its live text so the placeholder hides the instant
            // the user types and reappears only when the field is cleared.
            $imeActive = $this->surface->isImeTextviewActive()
                && $this->surface->focus()->isFocused($node->id);
            $value = $spec->value;
            if ($imeActive && $this->surface->getImeComposingText() !== '') {
                $value = $this->surface->getImeComposingText();
            }
            return new TextFieldSpec(
                value: $value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
                imeActive: $imeActive,
                control: $spec->control,
            );
        }

        if ($spec instanceof SearchFieldSpec) {
            $imeActive = $this->surface->isImeTextviewActive()
                && $this->surface->focus()->isFocused($node->id);
            $value = $spec->value;
            if ($imeActive && $this->surface->getImeComposingText() !== '') {
                $value = $this->surface->getImeComposingText();
            }
            return new SearchFieldSpec(
                value: $value,
                placeholder: $spec->placeholder,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
                showClear: $value !== '',
                imeActive: $imeActive,
                control: $spec->control,
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
            $controlValue = $control !== null ? $control->getValue() : '(no control)';
            // If TextAreaControl owns this spec, pull the live value from it
            // (TextAreaControl::syncSpec() keeps $value in sync with edits).
            $value = $control !== null ? $control->getValue() : $spec->value;

            // While the IME overlay (NSTextView) is active on this focused field,
            // drive the renderer from its live text. This makes the placeholder
            // disappear the instant the user types (even mid-IME-composition,
            // before the control value syncs) and reappear only when the field
            // is fully cleared — avoiding the typed-text / placeholder overlap.
            $imeActive = $this->surface->isImeTextviewActive()
                && $this->surface->focus()->isFocused($node->id);
            $cursor = $control !== null ? $control->getCursor() : $spec->cursor;
            if ($imeActive) {
                $imeText = $this->surface->getImeComposingText();
                if ($imeText !== '') {
                    $value = $imeText;
                }
                $cursor = $this->surface->getImeCaretPosition();
            }

            $this->surface->imeDbg("[Surface] withState: TextAreaSpec value=\"" . $value . "\" controlValue=\"" . $controlValue . "\" spec-value=\"" . $spec->value . "\" imeActive=" . ($imeActive ? 'true' : 'false') . " imeText=\"" . ($imeActive ? $this->surface->getImeComposingText() : '') . "\" control=" . ($control !== null ? ('yes#' . spl_object_id($control)) : 'no') . "\n");
            return new TextAreaSpec(
                value: $value,
                placeholder: $spec->placeholder,
                imeActive: $imeActive,
                enabled: $spec->enabled,
                focused: $this->surface->focus()->isFocused($node->id),
                hovered: $node->hovered,
                radius: $spec->radius,
                scrollY: $spec->scrollY,
                cursor: $cursor,
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
        // When the IME NSTextView is active, the NSTextView handles text input
        // directly (including multi-byte UTF-8 via OS IME), so the Surface
        // won't receive these events. This code path is for non-IME text fields.
        $focused = $this->surface->focus()->current();
        if ($focused !== null) {
            $textHandler = $this->surface->textHandlerFor($focused);
            if ($textHandler !== null && ($k->isPrintable() || $k->isBackspace() || $k->isEnter())) {
                $textHandler($k->isBackspace() ? '' : $k->char, $k->isBackspace());

                return true;
            }
        }

        // Arrow keys: scroll a focused scroll viewport, or move the caret in a textarea.
        // When the IME NSTextView is active, the NSTextView handles arrow keys
        // directly (including IME candidate navigation), so the Surface won't
        // receive these events for a focused TextArea.
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
