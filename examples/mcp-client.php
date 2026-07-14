<?php

/**
 * Minimal MCP client for the ui2 automation server (S2 + SSE).
 *
 * This is a dependency-free, standalone PHP script — it speaks plain HTTP to the
 * automation server started by `examples/automation-server.php` and does NOT need
 * libui / a display. It demonstrates the full MCP round-trip:
 *
 *   1. POST /mcp  (JSON-RPC 2.0 handshake)
 *        initialize → notifications/initialized → tools/list → resources/read
 *   2. GET  /mcp  (Server-Sent Events) — live server→client push:
 *        notifications/state_changed (new counter)  +  notifications/resources/updated
 *   3. Drive the UI from the client (tools/call ui_drive) and watch the resulting
 *      state change arrive over SSE in real time — no polling.
 *
 * Usage:
 *   # terminal 1 (needs a display):
 *   php examples/automation-server.php
 *   # terminal 2:
 *   php examples/mcp-client.php                  # defaults to http://127.0.0.1:18765
 *   php examples/mcp-client.php http://10.0.0.5:9000
 *
 * Equivalent with curl (SSE half):
 *   curl -N http://127.0.0.1:18765/mcp
 */

declare(strict_types=1);

/**
 * JSON-RPC 2.0 over HTTP client for the POST /mcp endpoint.
 *
 * Each call opens a fresh connection (the server answers with HTTP/1.0 +
 * Content-Length and closes), which is fine for request/response MCP calls.
 */
final class McpHttpClient
{
    public function __construct(private string $url) {}

    /** Send a JSON-RPC request that expects a response (carries an `id`). */
    public function call(string $method, array $params = []): ?array
    {
        static $id = 0;
        $id++;

        return $this->post([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ]);
    }

    /** Send a JSON-RPC notification (no `id`) — the server replies 202 with no body. */
    public function notify(string $method, array $params = []): void
    {
        $this->post([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ]);
    }

    private function post(array $payload): ?array
    {
        $body = (string) json_encode($payload);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($this->url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null; // notification → empty body, or connection error
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}

/**
 * Server-Sent Events consumer for the GET /mcp endpoint.
 *
 * Opens a single keep-alive HTTP/1.1 stream and yields one decoded event at a
 * time: `['event' => string, 'data' => string]`. Blocks up to `$timeout`
 * seconds via stream_select so it can be multiplexed with outgoing POST calls.
 */
final class SseStream
{
    private $sock;
    private string $buf = '';

    public function __construct(string $host, int $port)
    {
        $this->sock = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            5.0,
        );
        if ($this->sock === false) {
            throw new RuntimeException("Cannot connect to SSE endpoint {$host}:{$port} — is the server running? ({$errstr})");
        }
        stream_set_blocking($this->sock, false);

        $req = "GET /mcp HTTP/1.1\r\n"
            . "Host: {$host}:{$port}\r\n"
            . "Accept: text/event-stream\r\n"
            . "Connection: keep-alive\r\n\r\n";
        fwrite($this->sock, $req);
    }

    /** Return the next SSE event, or null on timeout / stream close. */
    public function next(float $timeout): ?array
    {
        $sec = (int) $timeout;
        $usec = (int) (($timeout - $sec) * 1_000_000);

        $read = [$this->sock];
        $write = [];
        $except = [];
        $n = @stream_select($read, $write, $except, $sec, $usec);
        if ($n === false || $n === 0) {
            return null;
        }

        $chunk = @fread($this->sock, 8192);
        if ($chunk === false || $chunk === '') {
            return null; // peer closed
        }
        $this->buf .= $chunk;

        $pos = strpos($this->buf, "\n\n");
        if ($pos === false) {
            return null; // incomplete event, wait for more bytes
        }

        $raw = substr($this->buf, 0, $pos);
        $this->buf = substr($this->buf, $pos + 2);

        $event = '';
        $data = '';
        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        if ($event === '' && $data === '') {
            return null;
        }

        return ['event' => $event, 'data' => $data];
    }

    public function close(): void
    {
        if (is_resource($this->sock)) {
            fclose($this->sock);
        }
    }
}

function handleEvent(array $ev): void
{
    $ts = date('H:i:s');
    if ($ev['event'] === 'endpoint') {
        echo "[{$ts}] SSE connected → endpoint {$ev['data']}\n";

        return;
    }
    if ($ev['event'] === 'message') {
        $msg = json_decode($ev['data'], true);
        if (! is_array($msg)) {
            return;
        }
        $method = $msg['method'] ?? '';
        if ($method === 'notifications/state_changed') {
            $state = $msg['params']['state'] ?? null;
            echo "[{$ts}] state_changed → " . json_encode($state, JSON_UNESCAPED_SLASHES) . "\n";
        } elseif ($method === 'notifications/resources/updated') {
            $uri = $msg['params']['uri'] ?? '?';
            echo "[{$ts}] resources/updated → {$uri} (client may re-read snapshot)\n";
        } else {
            echo "[{$ts}] message: {$ev['data']}\n";
        }

        return;
    }
    echo "[{$ts}] event={$ev['event']} data={$ev['data']}\n";
}

// ── bootstrap ───────────────────────────────────────────────────────────────

$base = $argv[1] ?? 'http://127.0.0.1:18765';
$mcpUrl = rtrim($base, '/') . '/mcp';
$host = (string) (parse_url($base, PHP_URL_HOST) ?: '127.0.0.1');
$port = (int) (parse_url($base, PHP_URL_PORT) ?: 18765);

echo "MCP client → {$mcpUrl}\n";

$http = new McpHttpClient($mcpUrl);

// 1) handshake ---------------------------------------------------------------
$init = $http->call('initialize', [
    'protocolVersion' => '2024-11-05',
    'capabilities' => new stdClass(),
    'clientInfo' => ['name' => 'ui2-php-client', 'version' => '1.0.0'],
]);
if ($init === null) {
    fwrite(STDERR, "Handshake failed — is the server running at {$base}?\n");
    exit(1);
}
$info = $init['result']['serverInfo'] ?? [];
echo "initialize → server: " . ($info['name'] ?? '?') . ' v' . ($info['version'] ?? '?') . "\n";

// Tell the server we finished initializing (notification, no response expected).
$http->notify('notifications/initialized');

// 2) discover tools + read the live snapshot ----------------------------------
$tools = $http->call('tools/list');
$toolNames = array_map(static fn (array $t): string => $t['name'], $tools['result']['tools'] ?? []);
echo 'tools/list → ' . implode(', ', $toolNames) . "\n";

$snap = $http->call('resources/read', ['uri' => 'ui://snapshot']);
$contents = $snap['result']['contents'][0]['text'] ?? '[]';
$nodeCount = count(json_decode($contents, true) ?: []);
echo "resources/read ui://snapshot → {$nodeCount} root node(s)\n";

// 3) open SSE and drive the UI, watching state changes live -------------------
echo "\nOpening SSE stream (GET /mcp)…\n";
try {
    $sse = new SseStream($host, $port);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$drives = 5;
$done = 0;
$lastDrive = 0.0;
$deadline = time() + 25;

while ($done < $drives && time() < $deadline) {
    $now = microtime(true);
    if ($now - $lastDrive > 1.0) {
        $lastDrive = $now;
        $done++;
        $r = $http->call('tools/call', [
            'name' => 'ui_drive',
            'arguments' => ['nodeId' => 'inc'],
        ]);
        $text = $r['result']['content'][0]['text'] ?? json_encode($r, JSON_UNESCAPED_SLASHES);
        echo "→ drove 'inc' (#{$done}): {$text}\n";
    }

    $ev = $sse->next(0.3);
    if ($ev !== null) {
        handleEvent($ev);
    }
}

$sse->close();
echo "\nDone — drove the counter {$done} time(s) and received live SSE state updates.\n";
