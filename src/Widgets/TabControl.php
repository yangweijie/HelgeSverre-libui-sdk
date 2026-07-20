<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TabSpec;
use Yangweijie\Ui2\Semantics\WidgetRole;

/**
 * A self-drawn tab strip with a swappable content panel, wired into a
 * {@see Surface}.
 *
 * The strip is a row of {@see TabSpec} leaves (role TabList); clicking a tab
 * activates it and swaps the contents of a panel slot node the control owns. The
 * panel content for each tab is supplied up-front as a {@see LayoutNode}, so the
 * active panel is just that node moved into the slot — no re-layout bookkeeping
 * beyond refreshing the focus order.
 *
 * When {@see $closable} is {@code true} every tab renders a × button at its
 * trailing edge; when {@see $addable} is {@code true} a + button appears at
 * the end of the strip.
 *
 * ```php
 * $tabs = new TabControl('main', [
 *     ['id' => 'home', 'label' => 'Home', 'content' => LayoutNode::leaf('home', new CardSpec())],
 *     ['id' => 'about','label' => 'About','content' => LayoutNode::leaf('about', new CardSpec())],
 * ]);
 * $tree->child($tabs->root());
 * $tabs->bind($surface);
 * ```
 */
final class TabControl
{
    private LayoutNode $bar;

    private LayoutNode $panelSlot;

    /** @var list<array{id:string,label:string,content:LayoutNode}> */
    private array $tabs;

    private int $active;

    private ?Surface $surface = null;

    /** @var callable(int):void|null */
    private $onChange = null;

    /** @var callable(int):void|null */
    private $onCloseTab = null;

    /** @var callable():void|null */
    private $onAddTab = null;

    /**
     * @param list<array{id:string,label:string,content:LayoutNode}> $tabs
     */
    public function __construct(
        private readonly string $name,
        array $tabs,
        int $active = 0,
        private readonly float $tabHeight = 38.0,
        private readonly float $panelHeight = 120.0,
        private readonly bool $closable = false,
        private readonly bool $addable = false,
    ) {
        $this->tabs = array_values($tabs);
        $this->active = $active;

        $this->bar = LayoutNode::row(gap: 2, padding: 0, id: "{$this->name}:bar", height: $tabHeight)->withRole(WidgetRole::TabList);
        $this->buildBar();

        $this->panelSlot = LayoutNode::column(gap: 8, padding: 8, id: "{$this->name}:panel", height: $panelHeight);
        if ($panelHeight > 0) {
            $this->rebuildPanel();
        }
    }

    /**
     * The combined tab-strip + panel node — drop this into a Surface tree.
     *
     * When {@see panelHeight} is ≤ 0 only the bar is returned (no panel slot).
     */
    public function root(): LayoutNode
    {
        if ($this->panelHeight <= 0) {
            return $this->bar;
        }

        return LayoutNode::column(gap: 0, id: $this->name, height: $this->tabHeight + $this->panelHeight)
            ->child($this->bar)
            ->child($this->panelSlot);
    }

    public function activeIndex(): int
    {
        return $this->active;
    }

    /** Register tab / close / add click handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        $this->rebindHandlers();

        return $this;
    }

    /** Activate a tab, swap the panel, and repaint. */
    public function setActive(int $index): void
    {
        if (! isset($this->tabs[$index]) || $index === $this->active) {
            return;
        }

        $this->active = $index;
        $this->rebuildBarAndHandlers();

        if ($this->panelHeight > 0) {
            $this->rebuildPanel();
        }

        if ($this->onChange !== null) {
            ($this->onChange)($index);
        }

        $this->surface?->refreshFocusables();
        $this->surface?->redraw();
    }

    /** @param callable(int):void $fn */
    public function onChange(callable $fn): static
    {
        $this->onChange = $fn;

        return $this;
    }

    /** @param callable(int):void $fn Receives the original tab index (before removal). */
    public function onCloseTab(callable $fn): static
    {
        $this->onCloseTab = $fn;

        return $this;
    }

    /** @param callable():void $fn */
    public function onAddTab(callable $fn): static
    {
        $this->onAddTab = $fn;

        return $this;
    }

    /**
     * Add a tab programmatically.
     *
     * Does NOT fire any callback — the caller initiated the add and knows what
     * happened (use {@see onAddTab} when the user clicks the + button).
     */
    public function addTab(?string $label = null): void
    {
        if ($label === null) {
            $label = 'tab-' . (count($this->tabs) + 1);
        }
        $this->tabs[] = ['id' => $label, 'label' => $label, 'content' => LayoutNode::leaf(null, null)];
        $this->active = count($this->tabs) - 1;

        $this->rebuildBarAndHandlers();
        if ($this->panelHeight > 0) {
            $this->rebuildPanel();
        }
        $this->surface?->redraw();
    }

    /**
     * Remove a tab programmatically.
     *
     * Fires {@see onCloseTab} with the original index (before removal) so the
     * consumer can mirror the change in its own data store.
     */
    public function removeTab(int $index): void
    {
        if (count($this->tabs) <= 1) {
            return;
        }

        array_splice($this->tabs, $index, 1);

        if ($index <= $this->active) {
            $this->active = max(0, $this->active - 1);
        }

        $this->rebuildBarAndHandlers();
        if ($this->panelHeight > 0) {
            $this->rebuildPanel();
        }

        if ($this->onCloseTab !== null) {
            ($this->onCloseTab)($index);
        }
        $this->surface?->redraw();
    }

    // ── Internal helpers ─────────────────────────────────────────────

    /** (Re)build the bar children — TabSpec leaves + optional × / + buttons. */
    private function buildBar(): void
    {
        $n = $this->name;
        $this->bar->children = [];

        foreach ($this->tabs as $i => $tab) {
            $this->bar->child(LayoutNode::leaf(
                "{$n}:tab:{$i}",
                new TabSpec(label: $tab['label'], active: $i === $this->active, closable: $this->closable && count($this->tabs) > 1),
                width: 110,
                height: $this->tabHeight,
            ));

        }

        if ($this->addable) {
            $this->bar->child(LayoutNode::leaf(
                "{$n}:tabadd",
                new ButtonSpec('+', 'soft'),
                width: 22,
                height: max(14, $this->tabHeight - 10),
            ));
        }
    }

    /** Re-register all click handlers on the Surface (tabs + close × + add +). */
    private function rebindHandlers(): void
    {
        if ($this->surface === null) {
            return;
        }

        $n = $this->name;
        foreach (array_keys($this->tabs) as $i) {
            $this->surface->onClick("{$n}:tab:{$i}", function () use ($i, $n): void {
                // If closable, check if click is in the × zone (right 20px of the tab)
                if ($this->closable && count($this->tabs) > 1 && $this->surface !== null) {
                    $rect = $this->surface->screenRectOf("{$n}:tab:{$i}");
                    if ($rect !== null) {
                        [$rx, $ry, $rw, $rh] = $rect;
                        $clickX = $this->surface->lastClickX();
                        if ($clickX >= $rx + $rw - 20.0) {
                            $this->removeTab($i);
                            return;
                        }
                    }
                }
                $this->setActive($i);
            });
        }

        if ($this->addable) {
            $this->surface->onClick("{$n}:tabadd", function (): void {
                if ($this->onAddTab !== null) {
                    ($this->onAddTab)();
                }
            });
        }
    }

    private function rebuildBarAndHandlers(): void
    {
        $this->buildBar();
        $this->rebindHandlers();
    }

    private function rebuildPanel(): void
    {
        if (isset($this->tabs[$this->active])) {
            $this->panelSlot->children = [clone $this->tabs[$this->active]['content']];
        }
    }
}
