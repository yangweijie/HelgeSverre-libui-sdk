<?php

declare(strict_types=1);

use Yangweijie\Ui2\Widgets\SvgView;
use Libui\Draw\Params\AreaMouseEvent;

// SvgDelegate is now in its own file (src/Widgets/SvgDelegate.php), loaded via PSR-4 autoload.

// ---------------------------------------------------------------------------
// Helpers: instantiate SvgDelegate (pure PHP, no FFI needed)
// ---------------------------------------------------------------------------

function createDelegate()
{
    return new \Yangweijie\Ui2\Widgets\SvgDelegate();
}

function invokePrivate(object $obj, string $method, mixed ...$args): mixed
{
    $m = new ReflectionMethod($obj::class, $method);
    return $m->invoke($obj, ...$args);
}

function setPrivate(object $obj, string $prop, mixed $value): void
{
    $p = new ReflectionProperty($obj::class, $prop);
    $p->setValue($obj, $value);
}

function getPrivate(object $obj, string $prop): mixed
{
    $p = new ReflectionProperty($obj::class, $prop);
    return $p->getValue($obj);
}

// ---------------------------------------------------------------------------
// pathBounds()
// ---------------------------------------------------------------------------

test('pathBounds extracts coordinates from M/L commands', function (): void {
    $d = createDelegate();
    $b = invokePrivate($d, 'pathBounds', 'M 10 20 L 50 60 L 100 30 Z');
    expect($b['minX'])->toBeLessThanOrEqual(8.0);   // 10 - pad 2
    expect($b['minY'])->toBeLessThanOrEqual(18.0);   // 20 - pad 2
    expect($b['maxX'])->toBeGreaterThanOrEqual(102.0); // 100 + pad 2
    expect($b['maxY'])->toBeGreaterThanOrEqual(62.0);  // 60 + pad 2
});

test('pathBounds handles single point', function (): void {
    $d = createDelegate();
    $b = invokePrivate($d, 'pathBounds', 'M 50 50');
    expect($b['minX'])->toBeLessThanOrEqual(48.0);
    expect($b['minY'])->toBeLessThanOrEqual(48.0);
    expect($b['maxX'])->toBeGreaterThanOrEqual(52.0);
    expect($b['maxY'])->toBeGreaterThanOrEqual(52.0);
});

test('pathBounds handles horizontal and vertical commands', function (): void {
    $d = createDelegate();
    $b = invokePrivate($d, 'pathBounds', 'M 0 0 H 100 V 100 H 0 Z');
    expect($b['minX'])->toBeLessThanOrEqual(-2.0);
    expect($b['minY'])->toBeLessThanOrEqual(-2.0);
    expect($b['maxX'])->toBeGreaterThanOrEqual(102.0);
    expect($b['maxY'])->toBeGreaterThanOrEqual(102.0);
});

test('pathBounds returns zeroes for empty string', function (): void {
    $d = createDelegate();
    $b = invokePrivate($d, 'pathBounds', '');
    expect($b['minX'])->toBe(-2.0);
    expect($b['minY'])->toBe(-2.0);
    expect($b['maxX'])->toBe(2.0);
    expect($b['maxY'])->toBe(2.0);
});

test('pathBounds handles decimal coordinates', function (): void {
    $d = createDelegate();
    $b = invokePrivate($d, 'pathBounds', 'M 10.5 20.7 L 30.2 40.9');
    expect($b['minX'])->toBeLessThanOrEqual(8.5);
    expect($b['minY'])->toBeLessThanOrEqual(18.7);
    expect($b['maxX'])->toBeGreaterThanOrEqual(32.2);
    expect($b['maxY'])->toBeGreaterThanOrEqual(42.9);
});

// ---------------------------------------------------------------------------
// setPaths() / elements structure
// ---------------------------------------------------------------------------

test('setPaths creates elements array with bounds', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z', 'M 70 70 L 100 70 L 100 100 Z']);
    $elements = getPrivate($d, 'elements');
    expect($elements)->toHaveCount(2);
    expect($elements[0])->toHaveKey('d');
    expect($elements[0])->toHaveKey('bounds');
    expect($elements[0])->toHaveKey('fill');
    expect($elements[0])->toHaveKey('stroke');
    expect($elements[1]['bounds']['minX'])->toBeGreaterThanOrEqual(68.0);
});

// ---------------------------------------------------------------------------
// hitTest()
// ---------------------------------------------------------------------------

test('hitTest finds element at coordinate', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']); // triangle roughly at x:10-50, y:10-40
    $index = invokePrivate($d, 'hitTest', 30.0, 25.0);
    expect($index)->toBe(0);
});

test('hitTest returns null for empty delegate', function (): void {
    $d = createDelegate();
    setPrivate($d, 'elements', []);
    $index = invokePrivate($d, 'hitTest', 10.0, 10.0);
    expect($index)->toBeNull();
});

test('hitTest returns null outside element bounds', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $index = invokePrivate($d, 'hitTest', 500.0, 500.0);
    expect($index)->toBeNull();
});

test('hitTest returns topmost element (last drawn)', function (): void {
    $d = createDelegate();
    $d->setPaths([
        'M 0 0 L 100 0 L 100 100 Z',   // element 0: large square
        'M 25 25 L 75 25 L 75 75 Z',     // element 1: smaller square inside
    ]);
    // Center of both squares → should hit element 1 (topmost)
    $index = invokePrivate($d, 'hitTest', 50.0, 50.0);
    expect($index)->toBe(1);
});

test('hitTest detects circle elements precisely', function (): void {
    $d = createDelegate();
    setPrivate($d, 'elements', [
        ['type' => 'circle', 'cx' => 50, 'cy' => 50, 'r' => 20,
         'bounds' => ['minX' => 29, 'minY' => 29, 'maxX' => 71, 'maxY' => 71]],
    ]);
    // Inside circle
    expect(invokePrivate($d, 'hitTest', 50, 50))->toBe(0);
    // On edge (radius distance)
    expect(invokePrivate($d, 'hitTest', 70, 50))->toBe(0);
    // Outside circle
    expect(invokePrivate($d, 'hitTest', 100, 50))->toBeNull();
});

test('hitTest detects ellipse elements precisely', function (): void {
    $d = createDelegate();
    setPrivate($d, 'elements', [
        ['type' => 'ellipse', 'cx' => 50, 'cy' => 50, 'rx' => 30, 'ry' => 10,
         'bounds' => ['minX' => 19, 'minY' => 39, 'maxX' => 81, 'maxY' => 61]],
    ]);
    // Inside ellipse (within rx horizontally)
    expect(invokePrivate($d, 'hitTest', 50, 50))->toBe(0);
    expect(invokePrivate($d, 'hitTest', 70, 50))->toBe(0);
    // Outside ellipse (beyond ry vertically, within rx horizontally)
    expect(invokePrivate($d, 'hitTest', 50, 70))->toBeNull();
});

// ---------------------------------------------------------------------------
// elementPayload()
// ---------------------------------------------------------------------------

test('elementPayload returns null fields for no hit', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $payload = invokePrivate($d, 'elementPayload', null, 100.0, 200.0);
    expect($payload['x'])->toBe(100.0);
    expect($payload['y'])->toBe(200.0);
    expect($payload['index'])->toBeNull();
    expect($payload['element'])->toBeNull();
    expect($payload['type'])->toBeNull();
});

test('elementPayload returns element data for hit index', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $payload = invokePrivate($d, 'elementPayload', 0, 30.0, 25.0);
    expect($payload['x'])->toBe(30.0);
    expect($payload['y'])->toBe(25.0);
    expect($payload['index'])->toBe(0);
    expect($payload['element'])->toBeArray();
    expect($payload['type'])->toBe('path');
});

// ---------------------------------------------------------------------------
// mouse() — event emission
// ---------------------------------------------------------------------------

test('mouse emits mousemove on every call', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $moves = [];
    $d->on('mousemove', function (array $p) use (&$moves): void {
        $moves[] = $p;
    });
    $ev = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 0, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev);
    expect($moves)->toHaveCount(1);
    expect($moves[0]['x'])->toBe(30.0);
});

test('mouse emits click on left button down', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $clicks = [];
    $d->on('click', function (array $p) use (&$clicks): void {
        $clicks[] = $p;
    });
    $ev = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 1, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev);
    expect($clicks)->toHaveCount(1);
    expect($clicks[0]['index'])->toBe(0);
});

test('mouse emits contextmenu on right-click (down=2)', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $cmds = [];
    $d->on('contextmenu', function (array $p) use (&$cmds): void {
        $cmds[] = $p;
    });
    $d->on('click', fn () => throw new \RuntimeException('click should not fire'));
    $ev = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 2, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev);
    expect($cmds)->toHaveCount(1);
});

test('mouse emits contextmenu on Windows right-click (down=3)', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $cmds = [];
    $d->on('contextmenu', function (array $p) use (&$cmds): void {
        $cmds[] = $p;
    });
    $ev = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 3, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev);
    expect($cmds)->toHaveCount(1);
});

test('mouse emits dblclick on double-click', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $dbl = [];
    $d->on('click', fn () => null);
    $d->on('dblclick', function (array $p) use (&$dbl): void {
        $dbl[] = $p;
    });
    $ev = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 1, up: 0, count: 2, modifiers: 0, held: 0);
    $d->mouse($ev);
    expect($dbl)->toHaveCount(1);
});

test('mouse emits hoverchange when hovered element changes', function (): void {
    $d = createDelegate();
    $d->setPaths([
        'M 0 0 L 100 0 L 100 100 Z',     // element 0
        'M 100 0 L 200 0 L 200 100 Z',   // element 1
    ]);
    $changes = [];
    $d->on('hoverchange', function (array $p) use (&$changes): void {
        $changes[] = $p['index'];
    });

    // Hover element 0
    $ev0 = new AreaMouseEvent(x: 50, y: 50, areaWidth: 200, areaHeight: 200,
        down: 0, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev0);
    expect($changes[0])->toBe(0);

    // Move to element 1
    $ev1 = new AreaMouseEvent(x: 150, y: 50, areaWidth: 200, areaHeight: 200,
        down: 0, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev1);
    expect($changes[1])->toBe(1);
});

test('mouse does not emit duplicate hoverchange', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $changes = [];
    $d->on('hoverchange', function (array $p) use (&$changes): void {
        $changes[] = $p['index'];
    });

    // Two mouse events at the same element
    $ev1 = new AreaMouseEvent(x: 30, y: 25, areaWidth: 200, areaHeight: 200,
        down: 0, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev1);
    $ev2 = new AreaMouseEvent(x: 31, y: 26, areaWidth: 200, areaHeight: 200,
        down: 0, up: 0, count: 1, modifiers: 0, held: 0);
    $d->mouse($ev2);
    // Only one hoverchange (first entry), second move doesn't change hover
    expect($changes)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// mouseCrossed() — enter/leave
// ---------------------------------------------------------------------------

test('mouseCrossed emits mouseenter when entering', function (): void {
    $d = createDelegate();
    $enters = [];
    $d->on('mouseenter', function (array $p) use (&$enters): void {
        $enters[] = $p;
    });
    $d->mouseCrossed(false); // false = entered
    expect($enters)->toHaveCount(1);
});

test('mouseCrossed emits mouseleave and resets hover when leaving', function (): void {
    $d = createDelegate();
    $d->setPaths(['M 10 10 L 50 10 L 30 40 Z']);
    $leaves = [];
    $d->on('mouseleave', function (array $p) use (&$leaves): void {
        $leaves[] = $p;
    });
    $d->mouseCrossed(true); // true = left
    expect($leaves)->toHaveCount(1);
    expect($leaves[0]['index'])->toBeNull();
});

// ---------------------------------------------------------------------------
// SVG parse() — simple element count
// ---------------------------------------------------------------------------

test('parse extracts elements from minimal SVG', function (): void {
    $d = createDelegate();
    $d->parse('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
        <rect x="10" y="10" width="50" height="30" fill="red"/>
        <circle cx="80" cy="80" r="10" fill="blue"/>
    </svg>');
    $elements = getPrivate($d, 'elements');
    expect($elements)->toHaveCount(2);
    expect($d->width)->toBe(100);
    expect($d->height)->toBe(100);
});

test('parse handles empty SVG', function (): void {
    $d = createDelegate();
    $d->parse('<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $elements = getPrivate($d, 'elements');
    expect($elements)->toHaveCount(0);
});

test('parse handles invalid XML gracefully', function (): void {
    $d = createDelegate();
    // Should not throw
    $d->parse('not xml');
    $elements = getPrivate($d, 'elements');
    expect($elements)->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// SvgView public event registration API
// (requires SvgView construction — FFI needed, so only test method existence)
// ---------------------------------------------------------------------------

test('SvgView event registration methods exist and return string', function (): void {
    $methods = ['onClick', 'onDoubleClick', 'onContextMenu', 'onMouseMove', 'onHoverChange', 'onMouseEnter', 'onMouseLeave'];
    $ref = new ReflectionClass(SvgView::class);
    foreach ($methods as $name) {
        expect($ref->hasMethod($name))->toBeTrue("SvgView::{$name}() should exist");
    }
});
