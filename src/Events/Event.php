<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Events;

/**
 * Marker interface for typed events emitted through {@see \Yangweijie\Ui2\EmitsEvents}.
 *
 * Typed events let automation / observability layers route by a stable name
 * instead of a bare string, and carry a structured payload. Existing string
 * events keep working — `EmitsEvents::emitEvent()` wraps an Event into a
 * `(name, event)` dispatch.
 *
 *     final class ClickedEvent implements Event
 *     {
 *         public function __construct(public readonly string $nodeId) {}
 *         public function name(): string { return 'clicked'; }
 *     }
 */
interface Event
{
    /** Stable event name used for subscription + routing. */
    public function name(): string;
}
