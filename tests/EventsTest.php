<?php

declare(strict_types=1);

use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Generated\Enum\ExtKey;
use Yangweijie\Ui2\Events\FocusManager;
use Yangweijie\Ui2\Events\KeyboardEvent;
use Yangweijie\Ui2\Events\PointerEvent;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;

test('PointerEvent classifies raw mouse events', function (): void {
    $down = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 1, 0, 1, 0, 0));
    expect($down->type)->toBe(PointerEvent::DOWN);
    expect($down->isLeftButton())->toBeTrue();
    expect($down->isPress())->toBeTrue();

    $up = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 1, 1, 0, 0));
    expect($up->type)->toBe(PointerEvent::UP);
    expect($up->isRelease())->toBeTrue();

    // A drag is a held button that was already held on the previous event.
    // PointerEvent derives press/drag/release from the held-bit *transition*,
    // so the prior held state must be supplied (as the SurfaceDelegate does).
    $drag = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 0, 1, 0, 1), prevHeld: 1);
    expect($drag->type)->toBe(PointerEvent::MOVE);
    expect($drag->isDrag())->toBeTrue();

    // A held button that was NOT held before is a press (covers down===0 builds).
    $pressViaHeld = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 0, 1, 0, 1), prevHeld: 0);
    expect($pressViaHeld->type)->toBe(PointerEvent::DOWN);
    expect($pressViaHeld->isPress())->toBeTrue();

    // No held bit and none before -> hover.
    $hover = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 0, 1, 0, 0));
    expect($hover->type)->toBe(PointerEvent::HOVER);
    expect($hover->isHover())->toBeTrue();
})->group('ffi');

test('PointerEvent exposes click count for double-clicks', function (): void {
    $dbl = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 1, 2, 0, 0));
    expect($dbl->clickCount)->toBe(2);
    expect($dbl->isDoubleClick())->toBeTrue();

    $single = PointerEvent::fromMouse(new AreaMouseEvent(10, 10, 200, 100, 0, 1, 1, 0, 0));
    expect($single->isDoubleClick())->toBeFalse();
})->group('ffi');

test('KeyboardEvent infers printable + extended intent', function (): void {
    // On this libui build, Enter/Space/Tab arrive via the ascii $key field.
    expect(KeyboardEvent::fromKey(new AreaKeyEvent(13, 0, 0, 0, false))->isEnter())->toBeTrue();
    expect(KeyboardEvent::fromKey(new AreaKeyEvent(9, 0, 0, 0, false))->isTab())->toBeTrue();
    expect(KeyboardEvent::fromKey(new AreaKeyEvent(32, 0, 0, 0, false))->isSpace())->toBeTrue();

    // Arrow keys arrive via the extKey field.
    $up = KeyboardEvent::fromKey(new AreaKeyEvent(0, ExtKey::Up->value, 0, 0, false));
    expect($up->isArrowUp())->toBeTrue();
    expect($up->isPressed())->toBeTrue();

    // A release of Enter is still the Enter *key* but not a press.
    $rel = KeyboardEvent::fromKey(new AreaKeyEvent(13, 0, 0, 0, true));
    expect($rel->isEnter())->toBeTrue();
    expect($rel->isPressed())->toBeFalse();
})->group('ffi');

test('KeyboardEvent detects Shift+Tab', function (): void {
    // Modifiers::Shift = 4
    $shiftTab = KeyboardEvent::fromKey(new AreaKeyEvent(9, 0, 0, 4, false));
    expect($shiftTab->isShiftTab())->toBeTrue();
    expect($shiftTab->isTab())->toBeTrue();
    expect($shiftTab->isShift())->toBeTrue();

    expect(KeyboardEvent::fromKey(new AreaKeyEvent(9, 0, 0, 0, false))->isShiftTab())->toBeFalse();
})->group('ffi');

test('FocusManager walks tab order with wrap-around', function (): void {
    $fm = new FocusManager();
    $fm->setTabOrder(['a', 'b', 'c']);

    $fm->focus('b');
    expect($fm->current())->toBe('b');
    expect($fm->isFocused('b'))->toBeTrue();

    $fm->focusNext();
    expect($fm->current())->toBe('c');

    $fm->focusNext(); // wraps past the last to the first
    expect($fm->current())->toBe('a');

    $fm->focusPrev(); // wraps back from the first to the last
    expect($fm->current())->toBe('c');

    $fm->focusPrev();
    expect($fm->current())->toBe('b');
})->group('ffi');

test('FocusManager ignores unknown ids and fires onChange only on change', function (): void {
    $fm = new FocusManager();
    $fm->setTabOrder(['a', 'b']);
    $changes = [];
    $fm->onChange(function (?string $old, ?string $new) use (&$changes): void {
        $changes[] = [$old ?? '', $new];
    });

    $fm->focus('x'); // unknown -> ignored
    expect($fm->current())->toBeNull();

    $fm->focus('a');
    $fm->focus('b');
    expect($changes)->toBe([['', 'a'], ['a', 'b']]);

    // re-focusing the same id must not fire again
    $fm->focus('b');
    expect(count($changes))->toBe(2);

    // re-supplying the tab order keeps a still-valid focus, drops an invalid one
    $fm->focus('b');
    $fm->setTabOrder(['a', 'c']);
    expect($fm->isFocused('b'))->toBeFalse();

    $fm->focus('a');
    $fm->setTabOrder(['a', 'c']);
    expect($fm->current())->toBe('a'); // retained
})->group('ffi');

test('LayoutNode::findAt returns the topmost leaf under a point', function (): void {
    $leaf = LayoutNode::leaf('btn', new ButtonSpec('Hi'), width: 100, height: 36);
    $leaf->x = 10; $leaf->y = 10; $leaf->w = 100; $leaf->h = 36;

    $container = LayoutNode::row()->child($leaf);
    $container->id = 'row';
    $container->x = 0; $container->y = 0; $container->w = 200; $container->h = 60;

    expect(LayoutNode::findAt($container, 50, 30))->toBe('btn'); // inside the leaf
    expect(LayoutNode::findAt($container, 5, 5))->toBe('row');    // empty container area
    expect(LayoutNode::findAt($container, 500, 500))->toBeNull(); // outside everything
})->group('ffi');

test('LayoutNode::focusables collects leaf ids in paint order', function (): void {
    $a = LayoutNode::leaf('a', new ButtonSpec('A'), width: 50, height: 30);
    $b = LayoutNode::leaf('b', new ButtonSpec('B'), width: 50, height: 30);
    $row = LayoutNode::row()->child($a)->child($b);
    $row->id = 'row';
    $container = LayoutNode::column()
        ->child($row)
        ->child(LayoutNode::leaf('c', new ButtonSpec('C'), width: 50, height: 30));

    expect(LayoutNode::focusables($container))->toBe(['a', 'b', 'c']);
})->group('ffi');
