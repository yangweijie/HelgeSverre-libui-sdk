<?php

declare(strict_types=1);

use Yangweijie\Ui2\EmitsEvents;
use Yangweijie\Ui2\Events\Event;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\State\AppRuntime;
use Yangweijie\Ui2\State\Model;
use Yangweijie\Ui2\State\Msg;
use Yangweijie\Ui2\State\StateChangedEvent;
use Yangweijie\Ui2\State\UpdateResult;
use Yangweijie\Ui2\System\AutomationCapability;
use Yangweijie\Ui2\System\AutomationServer;

// ---------------------------------------------------------------------------
// P1 — EmitsEvents bypass hooks
// ---------------------------------------------------------------------------

final class RecordingEmitter
{
    use EmitsEvents;

    public array $before = [];
    public array $after = [];

    protected function beforeEmit(string $event, mixed $data): void
    {
        $this->before[] = [$event, $data];
    }

    protected function afterEmit(string $event, mixed $data): void
    {
        $this->after[] = [$event, $data];
    }

    /** Test bridge: emit() is protected on the trait. */
    public function trigger(string $event, mixed $data = null): void
    {
        $this->emit($event, $data);
    }

    /** Test bridge: emitEvent() is protected on the trait. */
    public function triggerEvent(Event $event): void
    {
        $this->emitEvent($event);
    }
}

test('EmitsEvents fires before/after hooks and handlers', function () {
    $e = new RecordingEmitter();
    $seen = [];
    $e->on('ping', function ($data) use (&$seen) {
        $seen[] = $data;
    });

    $e->trigger('ping', 42);

    expect($e->before)->toBe([['ping', 42]]);
    expect($seen)->toBe([42]);
    expect($e->after)->toBe([['ping', 42]]);
});

test('EmitsEvents listeners() exposes the handler map', function () {
    $e = new RecordingEmitter();
    $e->on('a', fn () => null);
    $e->on('a', fn () => null);
    $e->on('b', fn () => null);

    $map = $e->listeners();
    expect(array_keys($map))->toBe(['a', 'b']);
    expect($map['a'])->toHaveCount(2);
});

test('EmitsEvents emitEvent wraps a typed Event', function () {
    $e = new RecordingEmitter();
    $captured = null;
    $e->on('greeted', function ($data) use (&$captured) {
        $captured = $data;
    });

    $event = new class ('world') implements Event {
        public function __construct(public readonly string $who) {}
        public function name(): string { return 'greeted'; }
    };
    $e->triggerEvent($event);

    expect($captured)->toBe($event);
    expect($captured->who)->toBe('world');
});

// ---------------------------------------------------------------------------
// P1 — AppRuntime state observability
// ---------------------------------------------------------------------------

final class TCounterModel implements Model
{
    public function __construct(public readonly int $count) {}
}

enum TCounterMsg: string implements Msg
{
    case Inc = 'inc';
}

test('AppRuntime broadcasts state.changed and exposes a snapshot', function () {
    $update = function (TCounterModel $m, TCounterMsg $msg): UpdateResult {
        return match ($msg) {
            TCounterMsg::Inc => UpdateResult::pure(new TCounterModel($m->count + 1)),
        };
    };

    $app = new AppRuntime(new TCounterModel(0), $update);

    $captured = null;
    $app->on('state.changed', function ($event) use (&$captured) {
        $captured = $event;
    });

    $next = $app->dispatch(TCounterMsg::Inc);

    expect($next->count)->toBe(1);
    expect($captured)->toBeInstanceOf(StateChangedEvent::class);
    expect($captured->oldModel->count)->toBe(0);
    expect($captured->newModel->count)->toBe(1);
    expect($captured->msg)->toBe(TCounterMsg::Inc);
    expect($app->snapshot())->toBe(['count' => 1]);
});

// ---------------------------------------------------------------------------
// S1 — AutomationServer (headless routing logic)
// ---------------------------------------------------------------------------

function automation_sample_node(): SemanticsNode
{
    $win = new SemanticsNode('win', WidgetRole::Dialog);
    $win->label = 'Demo';
    $btn = new SemanticsNode('save', WidgetRole::Button);
    $btn->label = 'Save';
    $win->add($btn);
    return $win;
}

test('AutomationServer /snapshot returns the semantics tree', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [automation_sample_node()],
        driveHandler: fn (string $id, array $p) => ['ok' => true, 'nodeId' => $id],
        stateProvider: fn () => null,
    );

    $resp = $server->handleRequest('GET', '/snapshot', '');
    $json = json_decode(substr($resp, strpos($resp, "\r\n\r\n") + 4), true);

    expect($json['windows'])->toHaveCount(1);
    expect($json['windows'][0]['role'])->toBe('dialog');
    expect($json['windows'][0]['label'])->toBe('Demo');
    expect($json['windows'][0]['children'][0]['id'])->toBe('save');
});

test('AutomationServer /state returns the provider payload', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn (string $id, array $p) => ['ok' => true],
        stateProvider: fn () => ['count' => 5],
    );

    $resp = $server->handleRequest('GET', '/state', '');
    $json = json_decode(substr($resp, strpos($resp, "\r\n\r\n") + 4), true);

    expect($json['count'])->toBe(5);
});

test('AutomationServer /state 404 when no provider', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn (string $id, array $p) => ['ok' => true],
    );

    $resp = $server->handleRequest('GET', '/state', '');
    expect(str_starts_with($resp, 'HTTP/1.0 404'))->toBeTrue();
});

test('AutomationServer /drive forwards to the drive handler', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: function (string $id, array $p) {
            return ['ok' => true, 'nodeId' => $id, 'value' => $p['value'] ?? null];
        },
    );

    $resp = $server->handleRequest('POST', '/drive', (string) json_encode(['nodeId' => 'save', 'value' => 'x']));
    $json = json_decode(substr($resp, strpos($resp, "\r\n\r\n") + 4), true);

    expect($json)->toBe(['ok' => true, 'nodeId' => 'save', 'value' => 'x']);
});

test('AutomationServer /drive 400 without nodeId', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn (string $id, array $p) => ['ok' => true],
    );

    $resp = $server->handleRequest('POST', '/drive', (string) json_encode([]));
    expect(str_starts_with($resp, 'HTTP/1.0 400'))->toBeTrue();
});

test('AutomationServer unknown route 404', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn (string $id, array $p) => ['ok' => true],
    );

    $resp = $server->handleRequest('GET', '/nope', '');
    expect(str_starts_with($resp, 'HTTP/1.0 404'))->toBeTrue();
});

test('AutomationCapability is available in CLI', function () {
    expect((new AutomationCapability())->available())->toBeTrue();
    expect((new AutomationCapability())->name())->toBe('automation');
});
