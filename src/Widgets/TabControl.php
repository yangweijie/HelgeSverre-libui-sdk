<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
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

    /**
     * @param list<array{id:string,label:string,content:LayoutNode}> $tabs
     */
    public function __construct(
        private readonly string $name,
        array $tabs,
        int $active = 0,
        private readonly float $tabHeight = 38.0,
        private readonly float $panelHeight = 120.0,
    ) {
        $this->tabs = array_values($tabs);
        $this->active = $active;

        $this->bar = LayoutNode::row(gap: 4, padding: 0, id: "{$this->name}:bar", height: $tabHeight)->withRole(WidgetRole::TabList);
        foreach ($this->tabs as $i => $tab) {
            $this->bar->child(LayoutNode::leaf(
                "{$this->name}:tab:{$i}",
                new TabSpec(label: $tab['label'], active: $i === $active),
                width: 110,
                height: $tabHeight,
            ));
        }

        $this->panelSlot = LayoutNode::column(gap: 8, padding: 8, id: "{$this->name}:panel", height: $panelHeight);
        $this->rebuildPanel();
    }

    /** The combined tab-strip + panel node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return LayoutNode::column(gap: 0, id: $this->name, height: $this->tabHeight + $this->panelHeight)
            ->child($this->bar)
            ->child($this->panelSlot);
    }

    public function activeIndex(): int
    {
        return $this->active;
    }

    /** Register tab click handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        foreach ($this->tabs as $i => $_) {
            $surface->onClick("{$this->name}:tab:{$i}", fn () => $this->setActive($i));
        }

        return $this;
    }

    /** Activate a tab, swap the panel, and repaint. */
    public function setActive(int $index): void
    {
        if (! isset($this->tabs[$index]) || $index === $this->active) {
            return;
        }

        $this->active = $index;

        foreach ($this->bar->children as $i => $leaf) {
            $spec = $leaf->spec;
            if (! $spec instanceof TabSpec) {
                continue;
            }
            $leaf->spec = new TabSpec(
                label: $spec->label,
                active: $i === $index,
                enabled: $spec->enabled,
                hovered: $spec->hovered,
                radius: $spec->radius,
            );
        }

        $this->rebuildPanel();

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

    private function rebuildPanel(): void
    {
        $this->panelSlot->children = [clone $this->tabs[$this->active]['content']];
    }
}
