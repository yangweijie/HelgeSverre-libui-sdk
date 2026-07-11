<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Events;

/**
 * Tracks which node id holds keyboard focus and walks a tab order.
 *
 * Deliberately decoupled from the layout tree: the Surface supplies the ordered
 * list of focusable ids (and re-supplies it whenever the tree changes), so this
 * class holds only ordering + current-focus logic and is trivially headless-testable.
 *
 * ```php
 * $fm = new FocusManager();
 * $fm->setTabOrder(['save', 'cancel', 'name']);
 * $fm->onChange(fn ($old, $new) => $surface->redraw());
 * $fm->focusNext();        // 'save' -> 'cancel'
 * $fm->focus('name');      // jumps directly
 * $fm->isFocused('name');  // true
 * ```
 */
final class FocusManager
{
    /** @var list<string> */
    private array $tabOrder = [];

    private ?string $current = null;

    /** @var callable(?string $old, ?string $new):void|null */
    private $onChange = null;

    /**
     * Replace the focusable id list (in tab order). De-duplicates and keeps the
     * current focus only if it is still present in the new order.
     *
     * @param list<string> $order
     */
    public function setTabOrder(array $order): void
    {
        $this->tabOrder = array_values(array_unique($order));

        if ($this->current !== null && !in_array($this->current, $this->tabOrder, true)) {
            $this->current = null;
        }
    }

    /** @return list<string> */
    public function tabOrder(): array
    {
        return $this->tabOrder;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function isFocused(string $id): bool
    {
        return $this->current === $id;
    }

    /**
     * Move focus to $id (or clear with null), firing onChange only when it
     * actually changes. Unknown ids are ignored.
     */
    public function focus(?string $id): void
    {
        if ($id !== null && !in_array($id, $this->tabOrder, true)) {
            return;
        }

        $old = $this->current;
        if ($old === $id) {
            return;
        }

        $this->current = $id;

        if ($this->onChange !== null) {
            ($this->onChange)($old, $id);
        }
    }

    /** Tab forward; wraps from the last id back to the first. */
    public function focusNext(): void
    {
        if ($this->tabOrder === []) {
            return;
        }

        $idx = $this->current === null ? -1 : array_search($this->current, $this->tabOrder, true);
        $next = $idx + 1;
        if ($idx === false || $next >= count($this->tabOrder)) {
            $next = 0;
        }

        $this->focus($this->tabOrder[$next]);
    }

    /** Shift+Tab backward; wraps from the first id to the last. */
    public function focusPrev(): void
    {
        if ($this->tabOrder === []) {
            return;
        }

        $idx = $this->current === null ? 0 : array_search($this->current, $this->tabOrder, true);
        if ($idx === false) {
            $idx = 0;
        }
        $prev = $idx - 1;
        if ($prev < 0) {
            $prev = count($this->tabOrder) - 1;
        }

        $this->focus($this->tabOrder[$prev]);
    }

    /**
     * Register a callback invoked on focus change. $oldId is null when focus
     * is moving from "nothing" (e.g. the very first Tab press).
     */
    public function onChange(callable $fn): void
    {
        $this->onChange = $fn;
    }
}
