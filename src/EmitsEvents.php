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
     * @param  string  $event  Event name
     * @param  mixed   $data   Optional payload passed to each handler
     */
    protected function emit(string $event, mixed $data = null): void
    {
        foreach ($this->listeners[$event] ?? [] as $handler) {
            $handler($data);
        }
    }
}
