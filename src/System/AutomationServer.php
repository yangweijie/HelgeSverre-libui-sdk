<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Libui\Loop;
use Yangweijie\Ui2\Logging\Log;
use Yangweijie\Ui2\Semantics\SemanticProvider;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\System\Mcp\McpServer;

/**
 * Embedded local automation server — the AI / test-driver hook point.
 *
 * Listens on 127.0.0.1 only (never exposed to the network) and serves the
 * observability contracts from §4 of docs/zh/design/observability-automation.md:
 *
 *   GET  /snapshot  → accessibility tree (SemanticsNode) of all registered roots
 *   GET  /state     → application state snapshot (when a stateProvider is given)
 *   POST /drive     → invoke an action on a node (delegated to driveHandler)
 *
 * The server is pure PHP (stream_socket_server + stream_select) and is driven by
 * a libui timer (Loop::repeat), so all work happens on the GUI thread — no extra
 * async runtime and no touching libui from another thread. Every request is
 * wrapped in try/catch because an uncaught exception would cross the FFI / event-
 * loop boundary and crash the process (lessons learned from the WebView bridge).
 *
 * The server is deliberately decoupled: it only consumes the §4 contracts
 * (SemanticsNode tree + AppRuntime state) and forwards write actions to an
 * injected driveHandler. It never reaches into widget internals.
 */
final class AutomationServer
{
    /** @var resource|null The listening server socket. */
    private $server = null;

    /** @var array<int,resource> Active client connections, keyed by (int) resource. */
    private array $conns = [];

    /** @var array<int,string> Per-connection read buffers, keyed by (int) resource. */
    private array $buffers = [];

    /** @var ?int Timer ID returned by Loop::repeat(). */
    private ?int $timerId = null;

    private int $port = 0;

    /** When true, an MCP protocol adapter is mounted at POST /mcp (S2). */
    private ?McpServer $mcpServer = null;

    /**
     * Open SSE subscriptions (keep-alive connections created via GET /mcp).
     * Keyed by (int) resource so the poll loop can push notifications to them.
     *
     * @var array<int,resource>
     */
    private array $sseConns = [];

    /** Queued SSE frames (already `event:/data:` formatted) waiting to be flushed. */
    private array $sseQueue = [];

    /** Per-connection pending outbound bytes (survives short writes). */
    private array $sseOut = [];

    /**
     * @param callable(): iterable         $rootsProvider Returns roots (Window | Surface | SemanticsNode | SemanticProvider) for /snapshot.
     * @param callable(string,array):array $driveHandler  (nodeId, payload) → response array for /drive.
     * @param (callable(): ?array)|null    $stateProvider Returns a state array for /state (optional).
     */
    public function __construct(
        private $rootsProvider,
        private $driveHandler,
        private $stateProvider = null,
        private int $intervalMs = 20,
        private bool $mcp = false,
    ) {
        if ($mcp) {
            $this->mcpServer = new McpServer($this->rootsProvider, $this->driveHandler, $this->stateProvider);
        }
    }

    /**
     * Bind the socket and register the poll timer on the libui event loop.
     *
     * Must be called from within the libui lifecycle (e.g. App::afterInit),
     * because Loop::repeat() schedules on the GUI thread.
     */
    public function start(int $port = 0): self
    {
        $addr = $port === 0 ? '127.0.0.1:0' : "127.0.0.1:{$port}";
        $this->server = @\stream_socket_server(
            "tcp://{$addr}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );
        if ($this->server === false) {
            throw new \RuntimeException("AutomationServer: failed to bind {$addr}: {$errstr} ({$errno})");
        }
        \stream_set_blocking($this->server, false);
        $this->port = $this->detectPort();

        $this->timerId = Loop::repeat($this->intervalMs, fn () => $this->poll());
        \register_shutdown_function(fn () => $this->stop());

        Log::event('automation.server.started', ['port' => $this->port]);

        return $this;
    }

    /** The bound port (useful when port 0 let the OS pick one). */
    public function port(): int
    {
        return $this->port;
    }

    /** Stop the server and unregister the timer. Safe to call multiple times. */
    public function stop(): void
    {
        if ($this->timerId !== null) {
            Loop::cancel($this->timerId);
            $this->timerId = null;
        }
        foreach (array_keys($this->conns) as $id) {
            $this->dropConn($id);
        }
        if ($this->server !== null) {
            @\fclose($this->server);
            $this->server = null;
        }
        Log::event('automation.server.stopped', []);
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * One poll tick: accept new connections, read buffered requests, respond.
     * Runs on the GUI thread (driven by Loop::repeat). Always returns true so the
     * timer keeps firing until stop() is called.
     */
    public function poll(): bool
    {
        try {
            if ($this->server === null) {
                return false;
            }

            // Push any queued server→client notifications to SSE subscribers
            // *before* the select; keeps firing even when no socket is readable.
            $this->flushSse();

            // Accept pending connections every tick with a non-blocking accept
            // rather than only when stream_select flags the listen socket. A
            // zero-timeout stream_select can intermittently fail to report the
            // listen socket as readable (observed on macOS loopback), which would
            // otherwise drop incoming connections.
            $conn = @\stream_socket_accept($this->server, 0);
            while ($conn !== false) {
                \stream_set_blocking($conn, false);
                $id = (int) $conn;
                $this->conns[$id] = $conn;
                $this->buffers[$id] = '';
                $conn = @\stream_socket_accept($this->server, 0);
            }

            // Read every connection each tick with a non-blocking fread instead
            // of gating on stream_select(0). A zero-timeout select can also miss
            // readable data on established connections (same macOS loopback
            // quirk), so we poll each conn directly and only drop a connection
            // on a hard read error or a genuine EOF.
            foreach ($this->conns as $id => $conn) {
                $data = @\fread($conn, 8192);
                if ($data === false) {
                    // Hard read error / peer closed — drop the connection.
                    $this->dropConn($id);
                    continue;
                }
                if ($data === '') {
                    $meta = @\stream_get_meta_data($conn);
                    if (($meta['eof'] ?? false)) {
                        $this->dropConn($id);
                    }
                    continue;
                }
                $this->buffers[$id] .= $data;

                $parsed = $this->tryParseRequest($this->buffers[$id]);
                if ($parsed === null) {
                    continue;
                }

                // Keep any leftover bytes for pipelined requests.
                $this->buffers[$id] = \substr($this->buffers[$id], $parsed['consumed']);

                if ($this->isSseEndpoint($parsed['method'], $parsed['path'])) {
                    // Consume the parsed request before upgrading, so any
                    // subsequent client bytes aren't re-parsed as a stale GET.
                    $this->buffers[$id] = \substr($this->buffers[$id], $parsed['consumed']);
                    // Upgrade this connection to a keep-alive SSE stream.
                    $this->openSse($id, $conn);
                    continue;
                }

                $response = $this->handleRequest($parsed['method'], $parsed['path'], $parsed['body']);
                $this->writeFully($conn, $response);
                $this->dropConn($id);
            }
        } catch (\Throwable $e) {
            Log::error('AutomationServer poll error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Build the HTTP response for a parsed request. Pure (no socket / no FFI),
     * so it is safe to call directly from tests.
     */
    public function handleRequest(string $method, string $path, string $body): string
    {
        try {
            $route = (string) \parse_url($path, PHP_URL_PATH);
            if ($route === '') {
                $route = '/';
            }

            if ($method === 'GET' && $route === '/snapshot') {
                return $this->jsonResponse(200, ['windows' => $this->buildSnapshot()]);
            }

            if ($method === 'GET' && $route === '/state') {
                if ($this->stateProvider === null) {
                    return $this->jsonResponse(404, ['error' => 'no state provider registered']);
                }
                $state = ($this->stateProvider)();
                if ($state === null) {
                    return $this->jsonResponse(404, ['error' => 'no state']);
                }
                return $this->jsonResponse(200, $state);
            }

            if ($method === 'POST' && $route === '/drive') {
                $payload = $body !== '' ? \json_decode($body, true) : [];
                if (!\is_array($payload)) {
                    return $this->jsonResponse(400, ['error' => 'invalid JSON body']);
                }
                $nodeId = $payload['nodeId'] ?? null;
                if (!\is_string($nodeId)) {
                    return $this->jsonResponse(400, ['error' => 'nodeId (string) required']);
                }
                $result = ($this->driveHandler)($nodeId, $payload['payload'] ?? $payload);
                return $this->jsonResponse(200, $result);
            }

            if ($method === 'POST' && $route === '/mcp') {
                if ($this->mcpServer === null) {
                    return $this->jsonResponse(404, ['error' => 'MCP not enabled']);
                }
                $mcpResp = $this->mcpServer->handle($body);
                if ($mcpResp === '') {
                    // JSON-RPC notification → no response body (HTTP 202).
                    return "HTTP/1.0 202 Accepted\r\n"
                        . "Content-Length: 0\r\n"
                        . "Connection: close\r\n\r\n";
                }

                return $this->rawJsonResponse(200, $mcpResp);
            }

            return $this->jsonResponse(404, ['error' => 'not found', 'path' => $route]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(500, ['error' => $e->getMessage()]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function buildSnapshot(): array
    {
        $out = [];
        foreach (($this->rootsProvider)() as $root) {
            $node = $this->toNode($root);
            if ($node !== null) {
                $out[] = $node->toArray();
            }
        }
        return $out;
    }

    private function toNode(mixed $root): ?SemanticsNode
    {
        if ($root instanceof SemanticsNode) {
            return $root;
        }
        if ($root instanceof SemanticProvider) {
            return $root->semantics();
        }
        return null;
    }

    // --- HTTP helpers -------------------------------------------------------

    private function jsonResponse(int $code, array $data): string
    {
        $body = (string) \json_encode($data);
        $len = \strlen($body);
        $reason = self::reasonPhrase($code);
        return "HTTP/1.0 {$code} {$reason}\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: {$len}\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $body;
    }

    /**
     * Like {@see jsonResponse()} but the JSON body is already serialized (used
     * for the MCP JSON-RPC envelope returned by McpServer::handle()).
     */
    private function rawJsonResponse(int $code, string $json): string
    {
        $len = \strlen($json);
        $reason = self::reasonPhrase($code);
        return "HTTP/1.0 {$code} {$reason}\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: {$len}\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $json;
    }

    private static function reasonPhrase(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            default => 'OK',
        };
    }

    /**
     * Parse one HTTP request out of the buffer. Returns null until the full
     * request (headers + Content-Length body) is available.
     *
     * @return array{method:string,path:string,headers:string,body:string,consumed:int}|null
     */
    private function tryParseRequest(string $buf): ?array
    {
        $headerEnd = \strpos($buf, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }
        $rawHeaders = \substr($buf, 0, $headerEnd);
        $lines = \explode("\r\n", $rawHeaders);
        $reqLine = $lines[0] ?? '';
        if (!\preg_match('#^(GET|POST|PUT|DELETE|HEAD) (\S+) HTTP/1\.[01]$#', $reqLine, $m)) {
            return null;
        }
        $method = $m[1];
        $path = $m[2];

        $contentLength = 0;
        foreach (\array_slice($lines, 1) as $line) {
            if (\preg_match('#^content-length:\s*(\d+)$#i', $line, $cl)) {
                $contentLength = (int) $cl[1];
            }
        }

        $bodyStart = $headerEnd + 4;
        $body = \substr($buf, $bodyStart);
        if (\strlen($body) < $contentLength) {
            return null; // Wait for the rest of the body.
        }

        return [
            'method' => $method,
            'path' => $path,
            'headers' => $rawHeaders,
            'body' => \substr($body, 0, $contentLength),
            'consumed' => $bodyStart + $contentLength,
        ];
    }

    private function dropConn(int $id): void
    {
        if (isset($this->sseConns[$id])) {
            unset($this->sseConns[$id]);
        }
        if (isset($this->conns[$id])) {
            @\fclose($this->conns[$id]);
            unset($this->conns[$id]);
        }
        unset($this->buffers[$id]);
        unset($this->sseOut[$id]);
    }

    // --- SSE (Server-Sent Events) push — S2 live notifications ------------

    /**
     * Push a state-changed notification to every connected SSE client.
     *
     * Wire this to a `AppRuntime` `state.changed` subscription (see
     * {@see \Libui\App::enableAutomation()}'s `$stateChangedHandler`). It is a
     * no-op when MCP is disabled or no SSE client is currently subscribed, so it
     * is safe to call on every state transition without overhead.
     */
    public function notifyStateChanged(): void
    {
        if ($this->mcpServer === null || $this->sseConns === []) {
            return;
        }

        // Standard MCP signal: a watched resource changed — client should re-read.
        $this->sseQueue[] = $this->sseFrame(
            'message',
            (string) \json_encode($this->mcpServer->resourceUpdatedNotification(), JSON_UNESCAPED_SLASHES),
        );

        if ($this->stateProvider !== null) {
            $state = ($this->stateProvider)();
            if ($state !== null) {
                $this->sseQueue[] = $this->sseFrame(
                    'message',
                    (string) \json_encode($this->mcpServer->stateChangedNotification($state), JSON_UNESCAPED_SLASHES),
                );
            }
        }
    }

    /** Number of currently subscribed SSE clients (for tests / introspection). */
    public function sseClientCount(): int
    {
        return \count($this->sseConns);
    }

    /** Drain and return queued SSE frames (test helper). */
    public function drainSseQueue(): array
    {
        $q = $this->sseQueue;
        $this->sseQueue = [];

        return $q;
    }

    /**
     * Upgrade a connection into a keep-alive SSE stream. Sends the SSE headers,
     * an `endpoint` announcement, then the current state so a fresh client has a
     * baseline before any future change arrives.
     */
    private function openSse(int $id, $conn): void
    {
        $this->sseConns[$id] = $conn;
        @\fwrite($conn, $this->sseHeaders());
        $this->sseQueue[] = $this->sseFrame('endpoint', '/mcp');
        // Push the current state immediately (sseConns is now non-empty).
        $this->notifyStateChanged();
        $this->flushSse();
    }

    /**
     * Flush every queued frame to all SSE subscribers. Runs on every poll tick
     * (driven by the libui timer) so notifications arrive promptly without the
     * client re-polling.
     */
    private function flushSse(): void
    {
        if ($this->sseQueue === [] || $this->sseConns === []) {
            return;
        }
        $frames = \implode('', $this->sseQueue);
        $this->sseQueue = [];
        foreach (array_keys($this->sseConns) as $id) {
            $this->sseOut[$id] = ($this->sseOut[$id] ?? '') . $frames;
        }
        foreach ($this->sseOut as $id => $pending) {
            if ($pending === '') {
                continue;
            }
            $conn = $this->sseConns[$id] ?? null;
            if ($conn === null) {
                unset($this->sseOut[$id]);
                continue;
            }
            // writeFully retries short writes / EAGAIN so the whole frame drains
            // within this tick; only a hard error drops the stream.
            if ($this->writeFully($conn, $pending)) {
                unset($this->sseOut[$id]);
            } else {
                $this->dropConn($id);
                unset($this->sseOut[$id]);
            }
        }
    }

    private function isSseEndpoint(string $method, string $path): bool
    {
        return $method === 'GET' && \parse_url($path, PHP_URL_PATH) === '/mcp';
    }

    /**
     * Write the full payload to a (possibly non-blocking) stream. Writes run in
     * blocking mode so a freshly-accepted SSE socket always drains every byte
     * within the current poll tick — even before the client has read anything
     * (the loopback kernel buffers it). The original blocking mode is restored
     * afterwards so the connection stays non-blocking for reads via stream_select.
     * Returns true once every byte is sent, false only on a hard write error.
     */
    private function writeFully($conn, string $data): bool
    {
        $meta = \stream_get_meta_data($conn);
        $wasBlocking = $meta['blocked'] ?? true;
        if (! $wasBlocking) {
            \stream_set_blocking($conn, true);
        }

        $offset = 0;
        $len = \strlen($data);
        $ok = true;
        while ($offset < $len) {
            $written = @\fwrite($conn, \substr($data, $offset));
            if ($written === false) {
                $ok = false;
                break;
            }
            if ($written === 0) {
                // Extremely unlikely in blocking mode; bail rather than spin.
                $ok = false;
                break;
            }
            $offset += $written;
        }

        if (! $wasBlocking) {
            \stream_set_blocking($conn, false);
        }

        return $ok;
    }

    private function sseHeaders(): string
    {
        return "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/event-stream\r\n"
            . "Cache-Control: no-cache\r\n"
            . "Connection: keep-alive\r\n"
            . "\r\n";
    }

    /**
     * Format a single SSE event: "event: <name>\ndata: <payload>\n\n".
     */
    private function sseFrame(string $event, string $data): string
    {
        return "event: {$event}\ndata: {$data}\n\n";
    }

    private function detectPort(): int
    {
        $name = @\stream_socket_get_name($this->server, false);
        if ($name === false || $name === '') {
            return 0;
        }
        $parts = \explode(':', $name);
        return (int) \end($parts);
    }
}
