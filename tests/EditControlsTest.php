<?php

declare(strict_types=1);

use Libui\Draw\Params\AreaMouseEvent;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PanelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrollViewSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrimSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaSpec;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Widgets\ScrollViewControl;
use Yangweijie\Ui2\Widgets\Surface;
use Yangweijie\Ui2\Widgets\TextAreaControl;

$tokens = new DesignTokens();

test('TextAreaRenderer draws a field box + border + hover wash', function () use ($tokens): void {
    $idle = (new TextAreaRenderer())->shapeCommands(new TextAreaSpec(value: 'hi'), $tokens, 240, 120);
    $hover = (new TextAreaRenderer())->shapeCommands(new TextAreaSpec(value: 'hi', hovered: true), $tokens, 240, 120);

    expect(array_filter($idle, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(1);
    expect(array_filter($idle, fn ($c) => $c instanceof StrokeRoundedRect))->toHaveCount(1);
    expect(array_filter($hover, fn ($c) => $c instanceof FillRoundedRect))->toHaveCount(2);
})->group('ffi');

test('TextAreaControl inserts, backspaces, and moves the caret', function (): void {
    $ta = new TextAreaControl('notes');
    $ta->insertChar('a');
    $ta->insertChar('b');
    $ta->insertChar('c');
    expect($ta->getValue())->toBe('abc');
    expect($ta->root()->spec->cursor)->toBe(3);

    $ta->moveCaret('left');
    expect($ta->root()->spec->cursor)->toBe(2);
    $ta->insertChar('X');
    expect($ta->getValue())->toBe('abXc');

    $ta->backspace();
    expect($ta->getValue())->toBe('abc');
    expect($ta->root()->spec->cursor)->toBe(2);
})->group('ffi');

test('TextAreaControl turns Enter into a newline', function (): void {
    $ta = new TextAreaControl('n');
    $ta->setValue('line');
    $ta->insertChar("\n");
    $ta->insertChar('2');
    expect($ta->getValue())->toBe("line\n2");
})->group('ffi');

test('TextAreaControl setValue clamps the caret to the end', function (): void {
    $ta = new TextAreaControl('n');
    $ta->setValue('hello world');
    expect($ta->root()->spec->cursor)->toBe(11);
})->group('ffi');

test('ScrollViewRenderer shows a thumb only when content overflows', function (): void {
    $r = new ScrollViewRenderer();

    $noOverflow = $r->verticalThumb(new ScrollViewSpec(contentHeight: 100, viewportHeight: 200), 200, 200);
    expect($noOverflow)->toBeNull();

    $thumb = $r->verticalThumb(new ScrollViewSpec(contentHeight: 400, viewportHeight: 200), 200, 200);
    expect($thumb)->not->toBeNull();
    [$x, $y, $w, $h] = $thumb;
    expect($x)->toBeGreaterThan(180.0); // sits in the right-hand gutter
    expect($h)->toBeGreaterThanOrEqual(ScrollViewRenderer::MIN_THUMB);
})->group('ffi');

test('ScrollViewRenderer maps a thumb centre back to a scroll offset', function (): void {
    $r = new ScrollViewRenderer();
    $spec = new ScrollViewSpec(contentHeight: 400, viewportHeight: 200);

    // Top of the track maps to scroll 0; bottom maps to the content max (200).
    expect($r->scrollYForThumbCenter($spec, ScrollViewRenderer::TRACK_INSET + 4, 200))->toBe(0.0);
    $bottomCenter = 200 - ScrollViewRenderer::TRACK_INSET - 2;
    expect($r->scrollYForThumbCenter($spec, $bottomCenter, 200))->toBe(200.0);
})->group('ffi');

test('ScrollViewControl builds a viewport + content and clamps scrolling', function (): void {
    $rows = [LayoutNode::leaf('r0', null, width: 300, height: 28)];
    $sv = new ScrollViewControl('log', $rows, width: 320, height: 200, contentHeight: 28 * 10);
    $root = $sv->root();

    expect($root->spec)->toBeInstanceOf(ScrollViewSpec::class);
    expect($root->children)->toHaveCount(1);                 // the content column
    expect($root->children[0]->children)->toHaveCount(1);    // the single row

    $sv->scrollTo(10000);
    expect($sv->scrollY())->toBe(80.0);

    $sv->scrollTo(-50);
    expect($sv->scrollY())->toBe(0.0);
})->group('ffi');

test('registry default registers the scroll + textarea renderers', function (): void {
    $registry = RendererRegistry::default();
    expect($registry->get('scroll_view'))->toBeInstanceOf(ScrollViewRenderer::class);
    expect($registry->get('text_area'))->toBeInstanceOf(TextAreaRenderer::class);
})->group('ffi');

test('semantics mapType covers scroll_view + text_area', function (): void {
    expect(SemanticsNode::mapType('scroll_view')->name)->toBe('Group');
    expect(SemanticsNode::mapType('text_area')->name)->toBe('TextBox');
})->group('ffi');

test('LayoutNode::findAt is scroll-aware for children inside a ScrollView', function (): void {
    $spacer = LayoutNode::leaf(null, null, width: 300, height: 150);
    $item = LayoutNode::leaf('item', new TextAreaSpec(value: 'x'), width: 300, height: 60);
    $sv = new ScrollViewControl('sv', [$spacer, $item], width: 320, height: 200, contentHeight: 500);
    $root = LayoutNode::column()->child($sv->root());
    FlexLayout::layout($root, 0, 0, 320, 600);

    // No scroll: the item is at layout y=150, so it is visible at y=150..210.
    expect(LayoutNode::findAt($root, 20, 170))->toBe('item');

    // Scroll down 100px: the item's visible y becomes 50..110.
    $sv->scrollTo(100);
    FlexLayout::layout($root, 0, 0, 320, 600);
    expect(LayoutNode::findAt($root, 20, 80))->toBe('item');

    // The same viewport point is now outside the item (the viewport still hits).
    expect(LayoutNode::findAt($root, 20, 170))->not->toBe('item');
    // A point below the viewport is empty.
    expect(LayoutNode::findAt($root, 20, 250))->toBeNull();
})->group('ffi');

test('ScrollView thumb drag routes through Surface and updates scrollY', function (): void {
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // Press on the right-hand scrollbar gutter/thumb area.
    $delegate->mouse(new AreaMouseEvent(314, 30, 320, 150, 1, 0, 1, 0, 0));
    $startY = $sv->scrollY();

    // Drag down 20px while holding the left button.
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 0, 1, 0, 1));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // Release and stop dragging.
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 1, 1, 0, 0));
})->group('ffi');

test('ScrollView thumb drag works when the viewport is inside a modal overlay', function (): void {
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);

    $panel = LayoutNode::column(id: 'overlay:panel');
    $panel->spec = new PanelSpec(bordered: true, radius: 6.0, elevation: 0.8);
    $panel->style->absolute = true;
    $panel->style->left = 20.0;
    $panel->style->top = 20.0;
    $panel->style->width = 320.0;
    $panel->style->height = 150.0;
    $panel->child($sv->root());

    $overlay = LayoutNode::column(id: 'overlay:scrim');
    $overlay->spec = new ScrimSpec(alpha: 0.12);
    $overlay->child($panel);

    $root = LayoutNode::column();
    $surface = new Surface($root);
    $surface->setOverlay($overlay);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // Right gutter of the overlayed scrollview: panel.x (20) + width - 6.
    $thumbX = 334.0;
    $thumbY = 50.0;

    $delegate->mouse(new AreaMouseEvent($thumbX, $thumbY, 400, 400, 1, 0, 1, 0, 0));
    $startY = $sv->scrollY();

    $delegate->mouse(new AreaMouseEvent($thumbX, $thumbY + 20, 400, 400, 0, 0, 1, 0, 1));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    $delegate->mouse(new AreaMouseEvent($thumbX, $thumbY + 20, 400, 400, 0, 1, 1, 0, 0));
})->group('ffi');

test('ScrollView drag still works when libui classifies the held-button move as HOVER (held bit clear)', function (): void {
    // Some libui backends report a move while a button is held with the
    // held bit cleared, so PointerEvent::fromMouse labels it HOVER instead
    // of MOVE. The Surface must still treat it as a drag while a press is
    // active (pressedId set), otherwise the thumb can never be dragged.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // Press on the right-hand scrollbar gutter/thumb area.
    $delegate->mouse(new AreaMouseEvent(314, 30, 320, 150, 1, 0, 1, 0, 0));
    $startY = $sv->scrollY();

    // Drag down 20px with the held bit CLEAR (mis-classified as HOVER).
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 0, 0, 0, 0));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // Release and stop dragging.
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 1, 0, 0, 0));
})->group('ffi');

test('ScrollView drag works when libui fires the PRESS frame with down=0, held=1', function (): void {
    // The user's macOS libui build reports the press frame with the down bit
    // left at 0 and only the held bit set. PointerEvent must derive the press
    // from the held-bit transition (prevHeld 0 -> held 1), otherwise isPress()
    // never fires, pressedId stays null, and every event falls through to hover
    // — so the scrollbar thumb (and sliders) can never be dragged.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // PRESS: down=0, held=1 (the offending build's press frame).
    $delegate->mouse(new AreaMouseEvent(314, 30, 320, 150, 0, 0, 0, 0, 1));
    $startY = $sv->scrollY();

    // Drag down 20px, still held=1 (no down bit anywhere).
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 0, 0, 0, 1));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // Release: held=0, up=0 (no explicit up bit either).
    $delegate->mouse(new AreaMouseEvent(314, 50, 320, 150, 0, 0, 0, 0, 0));
})->group('ffi');

test('ScrollView content-body drag pans the viewport', function (): void {
    // Grabbing the content itself (not the 12px scrollbar gutter) and dragging
    // must scroll the viewport — this is the natural "touch scroll" gesture
    // users expect and the original gap that made scrolling feel broken.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // Press on a content row — well left of the right-edge scrollbar gutter.
    $delegate->mouse(new AreaMouseEvent(30, 30, 320, 150, 1, 0, 1, 0, 0));
    $startY = $sv->scrollY();

    // Drag the content UP 20px (finger y: 30 -> 10) → viewport scrolls down.
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 0, 1, 0, 1));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // Release.
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 1, 1, 0, 0));
})->group('ffi');

test('a child with its own drag handler is not hijacked by body scroll', function (): void {
    // A widget that owns its own drag gesture (e.g. a Slider) must keep it even
    // when nested inside a ScrollView — body panning must not steal the gesture.
    $knob = LayoutNode::leaf('knob', null, width: 40, height: 40);
    $sv = new ScrollViewControl('panel', [$knob], width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $called = 0;
    $surface->onDrag('knob', function () use (&$called): void {
        $called++;
    });

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    $delegate->mouse(new AreaMouseEvent(20, 20, 320, 150, 1, 0, 1, 0, 0));
    $delegate->mouse(new AreaMouseEvent(20, 60, 320, 150, 0, 0, 1, 0, 1));
    $delegate->mouse(new AreaMouseEvent(20, 60, 320, 150, 0, 1, 1, 0, 0));

    expect($called)->toBeGreaterThan(0);
    expect($sv->scrollY())->toBe(0.0);
})->group('ffi');

test('ScrollView content-body drag works with press down=0,held=1 and move held=1', function (): void {
    // User's macOS libui fires the PRESS frame with down=0, held=1 (the #96
    // scenario). Moves keep held=1. Body drag must still pan the content.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // PRESS: down=0, held=1 (user's build).
    $delegate->mouse(new AreaMouseEvent(30, 30, 320, 150, 0, 0, 0, 0, 1));
    $startY = $sv->scrollY();

    // MOVE: held=1 (button still held).
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 0, 0, 0, 1));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // RELEASE: held=0.
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 0, 0, 0, 0));
})->group('ffi');

test('ScrollView content-body drag works with press down=0,held=1 and move held=0', function (): void {
    // Worst case: the user's libui fires the PRESS frame with down=0, held=1
    // but then MOVE frames with held CLEAR (#95 scenario). PointerEvent would
    // label such a move as UP, but isLeftButton() is false (button=0) so the
    // release branch is skipped and the drag branch (pressedId !== null) must
    // still run — otherwise content-body panning never engages.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // PRESS: down=0, held=1.
    $delegate->mouse(new AreaMouseEvent(30, 30, 320, 150, 0, 0, 0, 0, 1));
    $startY = $sv->scrollY();

    // MOVE: held=0 (the offending build's move frame).
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 0, 0, 0, 0));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // RELEASE: held=0.
    $delegate->mouse(new AreaMouseEvent(30, 10, 320, 150, 0, 0, 0, 0, 0));
})->group('ffi');

test('ScrollView scrollbar thumb drags on the user build (press down=0,held=1; move held=0; release up=1)', function (): void {
    // Exact event sequence reported from the real macOS libui build: the press
    // frame carries only held=1 (no down bit), MOVE frames clear the held bit
    // (mis-classified as HOVER), and release sends up=1. The Surface must keep
    // the drag STICKY through the move frames so the thumb actually scrolls.
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $sv = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    $root = LayoutNode::column()->child($sv->root());
    $surface = new Surface($root);
    $sv->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // PRESS on the scrollbar gutter/thumb (right edge), down=0, held=1.
    $delegate->mouse(new AreaMouseEvent(314, 30, 320, 150, 0, 0, 0, 0, 1));
    $startY = $sv->scrollY();

    // MOVE down 40px with the held bit CLEAR (the offending build's move frame).
    $delegate->mouse(new AreaMouseEvent(314, 70, 320, 150, 0, 0, 0, 0, 0));
    expect($sv->scrollY())->toBeGreaterThan($startY);

    // RELEASE: up=1, held=0.
    $delegate->mouse(new AreaMouseEvent(314, 70, 320, 150, 0, 1, 0, 0, 0));
    expect($sv->scrollY())->toBeGreaterThan($startY);
})->group('ffi');

test('nested ScrollView drag works after an ancestor has scrolled', function (): void {
    // Outer catalogue scrolled to near the bottom; inner panel nested inside it.
    // Regression: callDragHandler used raw layout coords for the inner node, so
    // once the outer was scrolled the inner's drag delta was offset by the
    // ancestor scroll and clamped to 0 (inner could never be dragged).
    $rows = [];
    foreach (range(0, 19) as $i) {
        $rows[] = LayoutNode::leaf("sv:row:{$i}", null, width: 300, height: 30);
    }
    $inner = new ScrollViewControl('panel', $rows, width: 320, height: 150, contentHeight: 600);
    // A tall spacer pushes the inner panel to layout y≈804 so that, once the
    // outer is scrolled to 804, the inner sits at the top of the viewport.
    $spacer = LayoutNode::leaf('spacer', null, width: 320, height: 804);
    $outer = new ScrollViewControl('catalog', [$spacer, $inner->root()], width: 320, height: 820, contentHeight: 1624);
    $root = LayoutNode::column()->child($outer->root());
    $surface = new Surface($root);
    $outer->bind($surface);
    $inner->bind($surface);

    $delegate = (new ReflectionClass($surface))->getProperty('delegate')->getValue($surface);

    // Simulate the catalogue already scrolled to its max (as in the real demo).
    $outer->scrollTo(804);
    FlexLayout::layout($root, 0, 0, 320, 820);

    // Locate the inner viewport node and compute its on-screen gutter coord
    // (layout y is shifted up by the ancestor's 804 scroll offset).
    $panelNode = LayoutNode::find($root, 'scroll:panel');
    $gutterX = $panelNode->x + 308;          // right-edge gutter (320 - 12)
    $gutterY = $panelNode->y - 804 + 40;     // on-screen y inside the viewport

    $delegate->mouse(new AreaMouseEvent($gutterX, $gutterY, 320, 820, 1, 0, 1, 0, 0));
    $startY = $inner->scrollY();

    // Drag the inner thumb down 40px while holding.
    $delegate->mouse(new AreaMouseEvent($gutterX, $gutterY + 40, 320, 820, 0, 0, 1, 0, 1));
    expect($inner->scrollY())->toBeGreaterThan($startY);

    $delegate->mouse(new AreaMouseEvent($gutterX, $gutterY + 40, 320, 820, 0, 1, 1, 0, 0));
})->group('ffi');
