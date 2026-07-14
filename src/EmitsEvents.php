<?php

declare(strict_types=1);

namespace Yangweijie\Ui2;

/**
 * Lightweight event emitter trait for UI components.
 *
 * Drop this into any class (most often an AreaDelegate subclass) to give it
 * a simple subscribe/emit pattern — no closure-based constructors, no
 * interfaces to implement.
 *
 *     class ToggleSwitch extends AreaDelegate
 *     {
 *         use EmitsEvents;
 *
 *         public function mouse(AreaMouseEvent $e): void
 *         {
 *             if ($e->isLeftButtonDown()) {
 *                 $this->on = !$this->on;
 *                 $this->redraw();
 *                 $this->emit('change', $this->on);
 *             }
 *         }
 *     }
 *
 *     $switch->on('change', fn (bool $on) => $label->setText($on ? 'ON' : 'OFF'));
 */
trait EmitsEvents
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /**
     * Register a handler for a named event.
     *
     * Multiple handlers for the same event are called in registration order.
     *
     * @param  string    $event   Event name (e.g. 'change', 'click')
     * @param  callable  $handler Receives the event payload, if any
     * @return $this
     */
    public function on(string $event, callable $handler): static
    {
        $this->listeners[$event][] = $handler;
        return $this;
    }

    /**
     * Fire an event, calling every registered handler in order.
     *
     * Subclasses / observers can hook in via {@see beforeEmit()} /
     * {@see afterEmit()} without altering business code — this is the zero-
     * intrusion tap point the automation / observability layer relies on.
     *
     * @param  string  $event  Event name
     * @param  mixed   $data   Optional payload passed to each handler
     */
    protected function emit(string $event, mixed $data = null): void
    {
        $this->beforeEmit($event, $data);
        foreach ($this->listeners[$event] ?? [] as $handler) {
            $handler($data);
        }
        $this->afterEmit($event, $data);
    }

    /**
     * Emit a typed {@see \Yangweijie\Ui2\Events\Event} object. The event's
     * {@see \Yangweijie\Ui2\Events\Event::name()} is used as the channel, and the
     * event itself is passed as the payload — enabling structured routing.
     */
    protected function emitEvent(\Yangweijie\Ui2\Events\Event $event): void
    {
        $this->emit($event->name(), $event);
    }

    /**
     * Hook invoked before any handler runs. Override to tap / log / transform
     * events. Default: no-op.
     */
    protected function beforeEmit(string $event, mixed $data): void
    {
    }

    /**
     * Hook invoked after all handlers have run. Override to tap / log events.
     * Default: no-op.
     */
    protected function afterEmit(string $event, mixed $data): void
    {
    }

    /**
     * Enumerate registered handlers (event name → list of callables). Lets the
     * automation layer introspect what an object can emit without reflection.
     *
     * @return array<string, list<callable>>
     */
    public function listeners(): array
    {
        return $this->listeners;
    }
}
