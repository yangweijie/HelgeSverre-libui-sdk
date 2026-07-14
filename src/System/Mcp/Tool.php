<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System\Mcp;

/**
 * An MCP tool definition: a stable name, a human description, a JSON-Schema
 * input shape, and a handler that turns arguments into a plain-array result.
 *
 * The handler signature is {@see McpServer::__construct()} compatible:
 *   (array<string,mixed> $args): array
 * McpServer wraps the returned array into MCP `content` for the client.
 */
final class Tool
{
    /**
     * @param string                    $name        Stable tool name (e.g. "ui_snapshot").
     * @param string                    $description Human description for the agent.
     * @param array<string,mixed>      $inputSchema JSON-Schema object for the tool's arguments.
     * @param callable(array):array    $handler    (args) → result payload array.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly mixed $handler,
    ) {
    }
}
