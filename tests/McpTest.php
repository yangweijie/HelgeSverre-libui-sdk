<?php

declare(strict_types=1);

use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\System\AutomationServer;
use Yangweijie\Ui2\System\Mcp\McpServer;

// ---------------------------------------------------------------------------
// S2 — MCP protocol adapter (headless JSON-RPC 2.0 handling)
// ---------------------------------------------------------------------------

function mcp_sample_node(): SemanticsNode
{
    $win = new SemanticsNode('win', WidgetRole::Dialog);
    $win->label = 'Demo';
    $btn = new SemanticsNode('save', WidgetRole::Button);
    $btn->label = 'Save';
    $win->add($btn);

    return $win;
}

function mcp_server(): McpServer
{
    return new McpServer(
        rootsProvider: fn () => [mcp_sample_node()],
        driveHandler: function (string $id, array $p) {
            return ['ok' => true, 'nodeId' => $id, 'value' => $p['value'] ?? null];
        },
        stateProvider: fn () => ['count' => 3],
    );
}

test('MCP initialize advertises protocol + capabilities', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2024-11-05'],
    ])), true);

    expect($resp['jsonrpc'])->toBe('2.0');
    expect($resp['id'])->toBe(1);
    expect($resp['result']['protocolVersion'])->toBe('2024-11-05');
    expect($resp['result']['capabilities'])->toHaveKeys(['tools', 'resources']);
    expect($resp['result']['serverInfo']['name'])->toBe('ui2-automation');
});

test('MCP tools/list returns the three contract tools', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
    ])), true);

    $names = array_column($resp['result']['tools'], 'name');
    expect($names)->toBe(['ui_snapshot', 'ui_get_state', 'ui_drive']);

    $drive = $resp['result']['tools'][2];
    expect($drive['inputSchema']['required'])->toBe(['nodeId']);
    expect($drive['inputSchema']['properties'])->toHaveKey('nodeId');
});

test('MCP tools/call ui_snapshot returns the tree', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
        'params' => ['name' => 'ui_snapshot', 'arguments' => []],
    ])), true);

    expect($resp['result']['isError'])->toBeFalse();
    $text = $resp['result']['content'][0]['text'];
    $tree = json_decode($text, true);
    expect($tree[0]['label'])->toBe('Demo');
    expect($tree[0]['children'][0]['id'])->toBe('save');
});

test('MCP tools/call ui_get_state returns state', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
        'params' => ['name' => 'ui_get_state', 'arguments' => []],
    ])), true);

    $state = json_decode($resp['result']['content'][0]['text'], true);
    expect($state['count'])->toBe(3);
});

test('MCP tools/call ui_drive forwards to the drive handler', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
        'params' => ['name' => 'ui_drive', 'arguments' => ['nodeId' => 'save', 'payload' => ['value' => 'x']]],
    ])), true);

    $out = json_decode($resp['result']['content'][0]['text'], true);
    expect($out)->toBe(['ok' => true, 'nodeId' => 'save', 'value' => 'x']);
});

test('MCP tools/call ui_drive errors without nodeId', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
        'params' => ['name' => 'ui_drive', 'arguments' => []],
    ])), true);

    expect($resp['result']['isError'])->toBeTrue();
    expect($resp['result']['content'][0]['text'])->toContain('nodeId');
});

test('MCP unknown method returns -32601', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 7, 'method' => 'bogus',
    ])), true);

    expect($resp['error']['code'])->toBe(-32601);
});

test('MCP parse error returns -32700', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle('{not json'), true);

    expect($resp['error']['code'])->toBe(-32700);
});

test('MCP ping returns empty result', function () {
    $s = mcp_server();
    $resp = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 8, 'method' => 'ping',
    ])), true);

    expect($resp['result'])->toBe([]);
});

test('MCP notification gets no response body', function () {
    $s = mcp_server();
    expect($s->handle(json_encode([
        'jsonrpc' => '2.0', 'method' => 'notifications/initialized',
    ])))->toBe('');
});

test('MCP resources/list + read return the snapshot resource', function () {
    $s = mcp_server();
    $list = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 9, 'method' => 'resources/list',
    ])), true);

    expect($list['result']['resources'][0]['uri'])->toBe('ui://snapshot');

    $read = json_decode($s->handle(json_encode([
        'jsonrpc' => '2.0', 'id' => 10, 'method' => 'resources/read',
        'params' => ['uri' => 'ui://snapshot'],
    ])), true);

    $text = $read['result']['contents'][0]['text'];
    expect(json_decode($text, true)[0]['label'])->toBe('Demo');
});

test('MCP batch handles mixed request + notification', function () {
    $s = mcp_server();
    $raw = json_encode([
        ['jsonrpc' => '2.0', 'id' => 11, 'method' => 'ping'],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
    ]);
    $arr = json_decode($s->handle($raw), true);

    expect($arr)->toHaveCount(1);
    expect($arr[0]['id'])->toBe(11);
});

// ---------------------------------------------------------------------------
// S1 + S2 integration — AutomationServer routes POST /mcp -> McpServer
// ---------------------------------------------------------------------------

function automation_mcp_server(): AutomationServer
{
    return new AutomationServer(
        rootsProvider: fn () => [mcp_sample_node()],
        driveHandler: fn (string $id, array $p) => ['ok' => true, 'nodeId' => $id],
        stateProvider: fn () => ['count' => 9],
        mcp: true,
    );
}

test('AutomationServer POST /mcp routes to the MCP adapter', function () {
    $server = automation_mcp_server();
    $body = json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
    ]);

    $resp = $server->handleRequest('POST', '/mcp', $body);
    $json = json_decode(substr($resp, strpos($resp, "\r\n\r\n") + 4), true);

    expect($json['result']['tools'][0]['name'])->toBe('ui_snapshot');
});

test('AutomationServer POST /mcp 404 when MCP disabled', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn (string $id, array $p) => ['ok' => true],
    );

    $resp = $server->handleRequest('POST', '/mcp', '{}');
    expect(str_starts_with($resp, 'HTTP/1.0 404'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// S2 SSE push — live server→client notifications (no polling)
// ---------------------------------------------------------------------------

function set_private(object $obj, string $prop, mixed $val): void
{
    $r = new \ReflectionProperty($obj, $prop);
    $r->setAccessible(true);
    $r->setValue($obj, $val);
}

test('SSE notifyStateChanged enqueues resource-updated + state_changed frames', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn () => [],
        stateProvider: fn () => ['count' => 7],
        mcp: true,
    );
    // Simulate a connected SSE subscriber with an in-memory stream.
    $fake = \fopen('php://memory', 'r+');
    set_private($server, 'sseConns', [(int) $fake => $fake]);

    $server->notifyStateChanged();
    $frames = $server->drainSseQueue();

    expect($frames)->toHaveCount(2);
    expect($frames[0])->toContain('notifications/resources/updated');
    expect($frames[0])->toContain('ui://snapshot');
    expect($frames[1])->toContain('notifications/state_changed');
    expect($frames[1])->toContain('"count":7');
    expect($frames[1])->toMatch('/^event: message\ndata: /');
});

test('SSE notifyStateChanged omits state_changed when no stateProvider', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn () => [],
        mcp: true,
    );
    $fake = \fopen('php://memory', 'r+');
    set_private($server, 'sseConns', [(int) $fake => $fake]);

    $server->notifyStateChanged();
    $frames = $server->drainSseQueue();

    expect($frames)->toHaveCount(1);
    expect($frames[0])->toContain('notifications/resources/updated');
    expect($frames[0])->not()->toContain('notifications/state_changed');
});

test('SSE notifyStateChanged is a no-op without subscribers', function () {
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn () => [],
        stateProvider: fn () => ['count' => 1],
        mcp: true,
    );
    $server->notifyStateChanged();

    expect($server->drainSseQueue())->toBe([]);
    expect($server->sseClientCount())->toBe(0);
});

test('SSE notifyStateChanged is a no-op when MCP is disabled', function () {
    $fake = \fopen('php://memory', 'r+');
    $server = new AutomationServer(
        rootsProvider: fn () => [],
        driveHandler: fn () => [],
    );
    set_private($server, 'sseConns', [(int) $fake => $fake]);

    $server->notifyStateChanged();
    expect($server->drainSseQueue())->toBe([]);
});

test('SSE GET /mcp opens a keep-alive stream and pushes live state_changed', function () {
    $state = ['count' => 1];

    // Drive poll() and read from the client in a single loop, mirroring how the
    // real libui timer repeatedly calls poll(). We retry the whole
    // connect+establish phase a few times because, on macOS loopback,
    // stream_socket_accept(0) can intermittently fail to surface a freshly
    // established connection for an entire attempt; a fresh socket pair usually
    // clears it. A genuine server-side failure still fails after all retries.
    $server = null;
    $client = null;
    $got = '';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $server = new AutomationServer(
            rootsProvider: fn () => [],
            driveHandler: fn () => [],
            stateProvider: static function () use (&$state) {
                return $state;
            },
            mcp: true,
        );

        $sock = @\stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        expect($sock)->toBeTruthy();
        \stream_set_blocking($sock, false);
        set_private($server, 'server', $sock);
        $name = \stream_socket_get_name($sock, false);
        $port = (int) \substr($name, \strrpos($name, ':') + 1);
        set_private($server, 'port', $port);

        $client = \stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1.0);
        \stream_set_blocking($client, false);
        \fwrite($client, "GET /mcp HTTP/1.1\r\nHost: localhost\r\n\r\n");

        $got = '';
        for ($i = 0; $i < 300; $i++) {
            $server->poll();
            $got .= (string) @\fread($client, 65536);
            if (\str_contains($got, 'notifications/state_changed')) {
                break;
            }
            // This attempt is doomed (connection never established) — bail early.
            if ($i > 50 && $server->sseClientCount() < 1) {
                break;
            }
            \usleep(500);
        }

        if (\str_contains($got, 'text/event-stream')) {
            break; // established successfully
        }
        if (\is_resource($client)) {
            @\fclose($client);
        }
        $server->stop();
    }

    expect($got)->toContain('text/event-stream');
    expect($got)->toContain('event: endpoint');
    expect($got)->toContain('notifications/state_changed');
    expect($got)->toContain('"count":1');
    expect($server->sseClientCount())->toBe(1);

    // A state transition pushes a fresh notification without the client polling.
    $state = ['count' => 2];
    $server->notifyStateChanged();
    $got2 = '';
    for ($i = 0; $i < 300 && !\str_contains($got2, '"count":2'); $i++) {
        $server->poll();
        $got2 .= (string) @\fread($client, 65536);
        if (!\str_contains($got2, '"count":2')) {
            \usleep(500);
        }
    }

    expect($got2)->toContain('notifications/state_changed');
    expect($got2)->toContain('"count":2');

    \fclose($client);
    $server->stop();
});
