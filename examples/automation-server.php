<?php

/**
 * Embedded automation server demo (S1 + S2).
 *
 * Starts a localhost automation server (see docs/zh/design/observability-automation.md).
 * Two protocols are served on the same port:
 *
 *   S1 — plain REST:
 *     GET  http://127.0.0.1:18765/snapshot   → accessibility tree (JSON)
 *     GET  http://127.0.0.1:18765/state      → app state snapshot (JSON)
 *     POST http://127.0.0.1:18765/drive       → {"nodeId":"save"} clicks the button
 *
 *   S2 — MCP (Model Context Protocol) over JSON-RPC 2.0 at POST /mcp:
 *     tools/list  → ui_snapshot, ui_get_state, ui_drive
 *     tools/call  → invoke any of those tools
 *     resources    → ui://snapshot (live accessibility tree)
 *   An MCP client (Claude Desktop, an LLM agent, etc.) points at
 *   http://127.0.0.1:18765/mcp and drives the UI through the tools.
 *
 *   S2 SSE push — live notifications without polling:
 *     GET  http://127.0.0.1:18765/mcp  (Accept: text/event-stream) opens a
 *     keep-alive SSE stream. After every AppRuntime state transition the server
 *     pushes `notifications/resources/updated` (ui://snapshot) and
 *     `notifications/state_changed` (the new counter) to every subscriber.
 *
 * Requires a display (libui GUI). From another terminal:
 *
 *   curl -s http://127.0.0.1:18765/snapshot | jq
 *   curl -s -X POST http://127.0.0.1:18765/drive -d '{"nodeId":"save"}'
 *   curl -s -X POST http://127.0.0.1:18765/mcp -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
 *   curl -N http://127.0.0.1:18765/mcp          # SSE: watch state.changed live
 *
 * A ready-made PHP MCP client that consumes this server (POST handshake + SSE)
 * lives at examples/mcp-client.php:
 *   php examples/mcp-client.php                  # drives 'inc', watches live state_changed
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\State\AppRuntime;
use Yangweijie\Ui2\State\Model;
use Yangweijie\Ui2\State\Msg;
use Yangweijie\Ui2\State\UpdateResult;
use Yangweijie\Ui2\Widgets\Surface;

// ── Elm-architecture counter: Model / Msg / Update ────────────────────────

final readonly class CounterModel implements Model
{
    public function __construct(
        public int $count = 0,
    ) {}
}

enum CounterMsg: string implements Msg
{
    case Inc = 'inc';
    case Dec = 'dec';
    case Reset = 'reset';
}

function counterUpdate(CounterModel $m, CounterMsg $msg): UpdateResult
{
    return match ($msg) {
        CounterMsg::Inc   => UpdateResult::pure(new CounterModel($m->count + 1)),
        CounterMsg::Dec   => UpdateResult::pure(new CounterModel($m->count - 1)),
        CounterMsg::Reset => UpdateResult::pure(new CounterModel(0)),
    };
}

$countLeaf = LayoutNode::leaf('count', new ButtonSpec('0', 'soft'), width: 80, height: 36);

$surface = new Surface(
    LayoutNode::row(gap: 8, padding: 12)
        ->child(LayoutNode::leaf('dec', new ButtonSpec('−', 'filled', radius: 18), width: 48, height: 36))
        ->child($countLeaf)
        ->child(LayoutNode::leaf('inc', new ButtonSpec('+', 'filled', radius: 18), width: 48, height: 36))
        ->child(LayoutNode::leaf('save', new ButtonSpec('Save', 'outline'), width: 100, height: 36))
        ->child(LayoutNode::leaf('cancel', new ButtonSpec('Cancel', 'outline'), width: 100, height: 36)),
);

// The running counter — every dispatch() emits `state.changed`, which we push
// to SSE subscribers through the automation server.
$app = new AppRuntime(new CounterModel(0), 'counterUpdate');

$surfaces = [$surface];
$surface->onClick('inc', static function () use ($app, $countLeaf, $surface): void {
    $m = $app->dispatch(CounterMsg::Inc);
    $countLeaf->spec = new ButtonSpec((string) $m->count, 'soft');
    $surface->redraw(); // self-drawn Surface does not auto-repaint on spec change
});
$surface->onClick('dec', static function () use ($app, $countLeaf, $surface): void {
    $m = $app->dispatch(CounterMsg::Dec);
    $countLeaf->spec = new ButtonSpec((string) $m->count, 'soft');
    $surface->redraw();
});
$surface->onClick('save', static function () {
    echo "Save clicked!\n";
});
$surface->onClick('cancel', static function () {
    echo "Cancel clicked!\n";
});

$window = new Window('Automation Demo', 440, 120, false);
$window->setChild($surface->root());
$window->onClosing(static fn () => true);

App::new()
    ->window($window)
    ->enableAutomation(
        port: 18765,
        stateProvider: static fn () => $app->snapshot(),
        driveHandler: static function (string $nodeId, array $payload) use ($surfaces): array {
            foreach ($surfaces as $s) {
                $handler = $s->handlerFor($nodeId);
                if ($handler !== null) {
                    $handler();
                    return ['ok' => true, 'nodeId' => $nodeId];
                }
            }
            return ['ok' => false, 'error' => "no handler for {$nodeId}"];
        },
        mcp: true,   // S2: also mount the MCP protocol adapter at POST /mcp
        // S2 SSE: subscribe the automation server to the counter's state changes
        // so it can push `notifications/state_changed` over GET /mcp SSE.
        stateChangedHandler: static function (\Yangweijie\Ui2\System\AutomationServer $server) use ($app): void {
            $app->on('state.changed', static fn () => $server->notifyStateChanged());
        },
    )
    ->run();
