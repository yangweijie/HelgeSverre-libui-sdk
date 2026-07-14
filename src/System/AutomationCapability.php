<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the embedded automation server.
 *
 * The server is pure PHP (stream_socket_server + stream_select), so the only
 * real dependency is that the function exists in this SAPI — no native bridge,
 * no FFI extension required.
 */
final class AutomationCapability implements Capability
{
    public function name(): string
    {
        return 'automation';
    }

    public function available(): bool
    {
        return \function_exists('stream_socket_server');
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'stream_socket_server() is not available in this SAPI.';
    }

    public function dependencies(): array
    {
        if ($this->available()) {
            return [];
        }

        return ['PHP function stream_socket_server() is not available'];
    }
}
