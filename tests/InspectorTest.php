<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\System\ComponentInspector;
use Yangweijie\Ui2\System\InspectorServer;

/**
 * Headless tests for the devtools ComponentInspector + InspectorServer.
 *
 * These run in the default (FFI-free) suite: ComponentInspector is pure PHP and
 * InspectorServer::handleRequest() is pure HTTP routing — no libui / display
 * required. The Surface draw/mouse hooks (which DO need a display) are covered
 * separately by the manual pick-mode flow.
 */

function inspector_tree(): LayoutNode
{
    // Explicit leaf sizes so headless FlexLayout produces non-zero rects
    // (intrinsic content sizing normally happens in the renderer).
    $save = LayoutNode::leaf('save', new ButtonSpec('Save'), 400.0, 40.0);
    $card = LayoutNode::leaf('card', new CardSpec(), 400.0, 80.0);

    return LayoutNode::column(id: 'root')
        ->child($save)
        ->child($card);
}

/** Extract the JSON body from a full HTTP response returned by handleRequest(). */
function http_json(string $raw): array
{
    $body = (string) substr($raw, (int) strpos($raw, "\r\n\r\n") + 4);

    return json_decode($body, true);
}

test('snapshot returns viewport, geometry, style and props', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    $snap = $ins->snapshot();

    expect($snap['viewport'])->toBe(['w' => 400.0, 'h' => 600.0]);
    expect($snap['selected'])->toBeNull();
    expect($snap['root']['id'])->toBe('root');
    expect($snap['root']['type'])->toBe('container');

    $ids = array_map(fn (array $c) => $c['id'], $snap['root']['children']);
    expect($ids)->toBe(['save', 'card']);

    // Geometry + props are populated for each leaf.
    $save = $snap['root']['children'][0];
    expect($save['type'])->toBe('button');
    expect($save['rect']['w'])->toBeGreaterThan(0);
    expect($save['rect']['h'])->toBeGreaterThan(0);

    $label = array_values(array_filter(
        $save['props'],
        fn (array $p) => $p['name'] === 'label',
    ))[0] ?? null;
    expect($label)->not->toBeNull();
    expect($label['value'])->toBe('Save');
    expect($label['editable'])->toBeTrue();
});

test('pick selects by id and rejects unknown ids', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    expect($ins->pick('card'))->toBe('card');
    expect($ins->selectedId())->toBe('card');
    expect($ins->highlightId())->toBe('card');

    // Unknown id is a no-op returning null.
    expect($ins->pick('nope'))->toBeNull();
    expect($ins->selectedId())->toBe('card');
});

test('pickAt hit-tests the topmost node at viewport coordinates', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    $save = $root->children[0];
    $cx = $save->x + $save->w / 2;
    $cy = $save->y + $save->h / 2;

    expect($ins->pickAt($cx, $cy))->toBe('save');
    expect($ins->selectedId())->toBe('save');

    // Coordinates outside any node return null.
    expect($ins->pickAt(-50, -50))->toBeNull();
});

test('getNode returns per-node detail and cannot delete the root', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    $detail = $ins->getNode('save');

    expect($detail['id'])->toBe('save');
    expect($detail['type'])->toBe('button');
    expect($detail['canDelete'])->toBeTrue();

    $rootDetail = $ins->getNode('root');
    expect($rootDetail['canDelete'])->toBeFalse();

    expect($ins->getNode('missing'))->toBeNull();
});

test('setStyle mutates a LayoutStyle field (typed)', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    expect($ins->setStyle('root', 'gap', 10))->toBeTrue();
    expect($root->style->gap)->toBe(10.0);

    // Unknown node / unknown prop fails.
    expect($ins->setStyle('nope', 'gap', 1))->toBeFalse();
    expect($ins->setStyle('root', 'no_such_field', 1))->toBeFalse();
});

test('setAttr mutates a readonly widget spec field via reflection', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    expect($ins->setAttr('save', 'label', 'Done'))->toBeTrue();
    // readonly bypassed → the spec value actually changed.
    expect($root->children[0]->spec->label)->toBe('Done');

    expect($ins->setAttr('nope', 'label', 'x'))->toBeFalse();
});

test('deleteAttr resets a property to its type default', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    expect($ins->setAttr('save', 'label', 'Temp'))->toBeTrue();
    expect($ins->deleteAttr('save', 'label'))->toBeTrue();

    // It is no longer the edited value, and a fresh set works (round-trip).
    expect($root->children[0]->spec->label)->not->toBe('Temp');
    expect($ins->setAttr('save', 'label', 'Again'))->toBeTrue();
    expect($root->children[0]->spec->label)->toBe('Again');
});

test('addChild appends a typed leaf; deleteNode removes a non-root node', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $ins = new ComponentInspector($root, 400, 600);
    expect($ins->addChild('root', 'label'))->toBeTrue();

    $hasLabel = false;
    foreach ($root->children as $c) {
        if ($c->spec !== null && $c->spec->type() === 'label') {
            $hasLabel = true;
            $newId = $c->id;
        }
    }
    expect($hasLabel)->toBeTrue();

    expect($ins->deleteNode($newId))->toBeTrue();
    $stillThere = false;
    foreach ($root->children as $c) {
        if ($c->spec !== null && $c->spec->type() === 'label') {
            $stillThere = true;
        }
    }
    expect($stillThere)->toBeFalse();

    // Root cannot be deleted.
    expect($ins->deleteNode('root'))->toBeFalse();
});

test('InspectorServer routes GET /snapshot and /node', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);

    $server = new InspectorServer(new ComponentInspector($root, 400, 600));

    $resp = http_json($server->handleRequest('GET', '/snapshot', ''), true);
    expect($resp['root']['id'])->toBe('root');

    $resp = http_json($server->handleRequest('GET', '/node?id=save', ''), true);
    expect($resp['id'])->toBe('save');
    expect($resp['type'])->toBe('button');

    // Unknown node → 404.
    $raw = $server->handleRequest('GET', '/node?id=ghost', '');
    expect((int) substr($raw, 9, 3))->toBe(404);
});

test('InspectorServer routes POST /pick /style /attr /structure', function (): void {
    $root = inspector_tree();
    FlexLayout::layout($root, 0, 0, 400, 600);
    $ins = new ComponentInspector($root, 400, 600);
    $server = new InspectorServer($ins);

    $resp = http_json($server->handleRequest('POST', '/pick', json_encode(['id' => 'card'])), true);
    expect($resp['id'])->toBe('card');
    expect($ins->selectedId())->toBe('card');

    $resp = http_json($server->handleRequest('POST', '/style', json_encode([
        'id' => 'root', 'prop' => 'gap', 'value' => 7,
    ])), true);
    expect($resp['ok'])->toBeTrue();
    expect($root->style->gap)->toBe(7.0);

    $resp = http_json($server->handleRequest('POST', '/attr', json_encode([
        'id' => 'save', 'name' => 'label', 'value' => 'Go',
    ])), true);
    expect($resp['ok'])->toBeTrue();
    expect($root->children[0]->spec->label)->toBe('Go');

    $resp = http_json($server->handleRequest('POST', '/structure', json_encode([
        'action' => 'add', 'id' => 'root', 'type' => 'badge',
    ])), true);
    expect($resp['ok'])->toBeTrue();

    // Unknown route → 404.
    $raw = $server->handleRequest('GET', '/unknown', '');
    expect((int) substr($raw, 9, 3))->toBe(404);
});
