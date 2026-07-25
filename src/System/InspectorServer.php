<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Libui\Loop;
use Yangweijie\Ui2\Logging\Log;

/**
 * HTTP server exposing the {@see ComponentInspector} F12 contract to an external
 * browser panel (the inspector UI lives in a normal browser tab, not inside the
 * ui2 window — see docs/zh/design/devtools-inspector.md).
 *
 * It runs on an independent 127.0.0.1 port (never the network) and is driven by
 * a libui timer (Loop::repeat), so all requests are served on the GUI thread —
 * the same model as {@see AutomationServer}. It deliberately does NOT extend
 * AutomationServer: that class is final and covered by the existing suite, and
 * decoupling the dev-tools inspector from the automation/observability surface
 * keeps a regression in one from breaking the other.
 *
 * The request loop mirrors AutomationServer's accept-every-tick pattern (the
 * macOS loopback quirk that intermittently drops single stream_select(0) reads),
 * but without SSE — the panel polls /snapshot after each edit, which is simpler
 * and sufficient for an interactive debugger.
 *
 * {@see self::handleRequest()} is pure (no socket, no FFI) and is the unit under
 * test; {@see self::start()}/{@see self::poll()} require the libui runtime and
 * are only exercised with a real display.
 *
 * Endpoints:
 *   GET  /snapshot        → full component tree (geometry + style + props)
 *   GET  /selected        → currently selected node id
 *   GET  /node?id=...     → per-node detail for the right-hand panel
 *   POST /pick            → {id} or {x,y} → select + highlight
 *   POST /highlight       → {id|null} → set the on-screen highlight
 *   POST /pickmode        → {on:bool} → enable/disable click-to-pick in the window
 *   POST /style           → {id,prop,value} → edit a LayoutStyle field
 *   POST /attr            → {id,name,value} → edit/add a widget/node property
 *   POST /deleteattr      → {id,name} → reset a property to its type default
 *   POST /structure       → {action:"add"|"delete",id,type?} → restructure tree
 */
final class InspectorServer
{
    /** @var resource|null The listening server socket. */
    private $server = null;

    /** @var array<int,resource> */
    private array $conns = [];

    /** @var array<int,string> */
    private array $buffers = [];

    private ?int $timerId = null;

    private int $port = 0;

    public function __construct(
        private ComponentInspector $inspector,
        private int $intervalMs = 20,
    ) {
    }

    /**
     * Bind the socket and register the poll timer on the libui event loop.
     * Must be called from within the libui lifecycle (e.g. App::afterInit).
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
            throw new \RuntimeException("InspectorServer: failed to bind {$addr}: {$errstr} ({$errno})");
        }
        \stream_set_blocking($this->server, false);
        $this->port = $this->detectPort();

        $this->timerId = Loop::repeat($this->intervalMs, fn () => $this->poll());
        \register_shutdown_function(fn () => $this->stop());

        Log::event('inspector.server.started', ['port' => $this->port]);

        return $this;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function stop(): void
    {
        if ($this->timerId !== null) {
            Loop::cancel($this->timerId);
            $this->timerId = null;
        }
        foreach (array_keys($this->conns) as $id) {
            $this->dropConn($id);
        }
        if (is_resource($this->server)) {
            @\fclose($this->server);
        }
        $this->server = null;
        Log::event('inspector.server.stopped', []);
    }

    public function __destruct()
    {
        $this->stop();
    }

    /** One poll tick: accept, read, respond. Runs on the GUI thread. */
    public function poll(): bool
    {
        try {
            if ($this->server === null) {
                return false;
            }

            $conn = @\stream_socket_accept($this->server, 0);
            while ($conn !== false) {
                \stream_set_blocking($conn, false);
                $id = (int) $conn;
                $this->conns[$id] = $conn;
                $this->buffers[$id] = '';
                $conn = @\stream_socket_accept($this->server, 0);
            }

            foreach ($this->conns as $id => $conn) {
                $data = @\fread($conn, 8192);
                if ($data === false) {
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
                $this->buffers[$id] = \substr($this->buffers[$id], $parsed['consumed']);

                $response = $this->handleRequest($parsed['method'], $parsed['path'], $parsed['body']);
                $this->writeFully($conn, $response);
                $this->dropConn($id);
            }
        } catch (\Throwable $e) {
            Log::error('InspectorServer poll error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Build the HTTP response for a parsed request. Pure (no socket / no FFI),
     * so it is safe to call directly from tests.
     *
     * @return array{method:string,path:string,body:string,consumed:int}|null
     */
    public function handleRequest(string $method, string $path, string $body): string
    {
        try {
            $route = (string) \parse_url($path, PHP_URL_PATH);
            if ($route === '') {
                $route = '/';
            }
            \parse_str((string) \parse_url($path, PHP_URL_QUERY), $query);

            if ($method === 'GET' && $route === '/snapshot') {
                return $this->json(200, $this->inspector->snapshot());
            }

            if ($method === 'GET' && $route === '/selected') {
                return $this->json(200, ['selected' => $this->inspector->selectedId()]);
            }

            if ($method === 'GET' && $route === '/node') {
                $id = $query['id'] ?? null;
                if (! \is_string($id)) {
                    return $this->json(400, ['error' => 'id (string) required']);
                }
                $node = $this->inspector->getNode($id);
                if ($node === null) {
                    return $this->json(404, ['error' => 'node not found', 'id' => $id]);
                }

                return $this->json(200, $node);
            }

            $payload = $body !== '' ? \json_decode($body, true) : [];
            if (! \is_array($payload)) {
                return $this->json(400, ['error' => 'invalid JSON body']);
            }

            if ($method === 'POST' && $route === '/pick') {
                if (isset($payload['id'])) {
                    $id = $this->inspector->pick((string) $payload['id']);
                } elseif (isset($payload['x'], $payload['y'])) {
                    $id = $this->inspector->pickAt((float) $payload['x'], (float) $payload['y']);
                } else {
                    return $this->json(400, ['error' => 'id or x,y required']);
                }

                return $this->json($id === null ? 404 : 200, ['id' => $id]);
            }

            if ($method === 'POST' && $route === '/highlight') {
                $id = isset($payload['id']) ? (string) $payload['id'] : null;
                $this->inspector->highlight($id);

                return $this->json(200, ['highlight' => $this->inspector->highlightId()]);
            }

            if ($method === 'POST' && $route === '/pickmode') {
                $on = (bool) ($payload['on'] ?? false);
                $this->inspector->setPickMode($on);

                return $this->json(200, ['pickMode' => $this->inspector->isPickMode()]);
            }

            if ($method === 'POST' && $route === '/style') {
                $id = $payload['id'] ?? null;
                $prop = $payload['prop'] ?? null;
                if (! \is_string($id) || ! \is_string($prop)) {
                    return $this->json(400, ['error' => 'id and prop (string) required']);
                }
                $ok = $this->inspector->setStyle($id, $prop, $payload['value'] ?? null);

                return $this->json($ok ? 200 : 404, ['ok' => $ok]);
            }

            if ($method === 'POST' && $route === '/attr') {
                $id = $payload['id'] ?? null;
                $name = $payload['name'] ?? null;
                if (! \is_string($id) || ! \is_string($name)) {
                    return $this->json(400, ['error' => 'id and name (string) required']);
                }
                $ok = $this->inspector->setAttr($id, $name, $payload['value'] ?? null);

                return $this->json($ok ? 200 : 404, ['ok' => $ok]);
            }

            if ($method === 'POST' && $route === '/deleteattr') {
                $id = $payload['id'] ?? null;
                $name = $payload['name'] ?? null;
                if (! \is_string($id) || ! \is_string($name)) {
                    return $this->json(400, ['error' => 'id and name (string) required']);
                }
                $ok = $this->inspector->deleteAttr($id, $name);

                return $this->json($ok ? 200 : 404, ['ok' => $ok]);
            }

            if ($method === 'POST' && $route === '/structure') {
                $action = $payload['action'] ?? null;
                $id = $payload['id'] ?? null;
                if (! \is_string($action) || ! \is_string($id)) {
                    return $this->json(400, ['error' => 'action and id (string) required']);
                }
                if ($action === 'add') {
                    $type = $payload['type'] ?? 'button';
                    $ok = $this->inspector->addChild($id, \is_string($type) ? $type : 'button');
                } elseif ($action === 'delete') {
                    $ok = $this->inspector->deleteNode($id);
                } else {
                    return $this->json(400, ['error' => 'unknown action', 'action' => $action]);
                }

                return $this->json($ok ? 200 : 404, ['ok' => $ok]);
            }

            return $this->json(404, ['error' => 'not found', 'path' => $route]);
        } catch (\Throwable $e) {
            return $this->json(500, ['error' => $e->getMessage()]);
        }
    }

    // --- HTTP helpers (kept private + self-contained) ----------------------

    private function json(int $code, array $data): string
    {
        $body = (string) \json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $len = \strlen($body);
        $reason = self::reasonPhrase($code);

        return "HTTP/1.0 {$code} {$reason}\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: {$len}\r\n"
            . "Access-Control-Allow-Origin: *\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $body;
    }

    /**
     * Parse one HTTP request out of the buffer. Returns null until the full
     * request (headers + Content-Length body) is available.
     *
     * @return array{method:string,path:string,body:string,consumed:int}|null
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
        if (! \preg_match('#^(GET|POST|PUT|DELETE|HEAD) (\S+) HTTP/1\.[01]$#', $reqLine, $m)) {
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
            return null;
        }

        return [
            'method' => $method,
            'path' => $path,
            'body' => \substr($body, 0, $contentLength),
            'consumed' => $bodyStart + $contentLength,
        ];
    }

    private function dropConn(int $id): void
    {
        if (isset($this->conns[$id])) {
            @\fclose($this->conns[$id]);
            unset($this->conns[$id]);
        }
        unset($this->buffers[$id]);
    }

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
            if ($written === false || $written === 0) {
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
