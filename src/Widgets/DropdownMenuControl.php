<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PanelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrimSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;

/**
 * A self-drawn dropdown menu (.dropdown_menu), wired into a {@see Surface}.
 *
 * A {@see SelectSpec} trigger box (with caret) toggles a DROPDOWN PANEL of
 * {@see ListRowSpec} options. The panel is shown as a Surface overlay anchored
 * *below* the trigger — it floats over the other widgets (covering them)
 * instead of pushing the layout around, and dismisses on any outside click.
 * Selecting an option updates the trigger label, fires onSelect, and closes.
 *
 * ```php
 * $menu = new DropdownMenuControl('sort', ['Name', 'Size', 'Date'], selected: 0);
 * $tree->child($menu->root());
 * $menu->bind($surface)->onSelect(fn ($i, $label) => sortBy($label));
 * ```
 */
final class DropdownMenuControl
{
    private LayoutNode $root;

    private LayoutNode $trigger;

    /** @var list<LayoutNode> */
    private array $optionRows = [];

    private array $options;

    private int $selected;

    private bool $open = false;

    private ?Surface $surface = null;

    /** @var callable(int,string):void|null */
    private $onSelect = null;

    /**
     * @param list<string> $options
     */
    public function __construct(
        private readonly string $name,
        array $options,
        int $selected = 0,
        private float $width = 200.0,
        private float $rowHeight = 34.0,
    ) {
        $this->options = array_values($options);
        $this->selected = $selected;

        $this->trigger = LayoutNode::leaf(
            "{$this->name}:trigger",
            new SelectSpec(value: $this->options[$selected] ?? ''),
            width: $this->width,
            height: $this->rowHeight,
        );
        $this->root = LayoutNode::column(gap: 0, id: $this->name, height: $this->rowHeight)->child($this->trigger);
    }

    /** The control's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function selectedIndex(): int
    {
        return $this->selected;
    }

    /** Register trigger handler on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;
        $surface->onClick($this->trigger->id, fn () => $this->toggle());

        return $this;
    }

    /** Build the absolutely-positioned panel node (not yet anchored). */
    private function buildPanel(): LayoutNode
    {
        $panelH = count($this->options) * $this->rowHeight
            + max(0, count($this->options) - 1) * 2
            + 8;

        $panel = LayoutNode::column(gap: 2, padding: 4, id: "{$this->name}:panel");
        $panel->spec = new PanelSpec(bordered: true, radius: 6.0, elevation: 0.8);
        $panel->style->absolute = true;
        $panel->style->width = $this->width;
        $panel->style->height = $panelH;

        $this->optionRows = [];
        foreach ($this->options as $i => $label) {
            $row = LayoutNode::leaf(
                "{$this->name}:opt:{$i}",
                new ListRowSpec(label: $label, selected: $i === $this->selected),
                height: $this->rowHeight,
            );
            $this->optionRows[] = $row;
            $panel->child($row);
        }

        return $panel;
    }

    /** Show the dropdown panel as an overlay anchored below the trigger. */
    public function open(): void
    {
        if ($this->open || $this->surface === null) {
            return;
        }
        $rect = $this->surface->screenRectOf($this->name);
        if ($rect === null) {
            return;
        }

        $panel = $this->buildPanel();
        $panelH = $panel->style->height;
        $left = $rect[0];
        $top = $rect[1] + $rect[3];
        // Flip above the trigger when there isn't room below.
        if ($top + $panelH > $this->surface->lastAreaHeight()) {
            $top = max(0.0, $rect[1] - $panelH);
        }
        $panel->style->left = $left;
        $panel->style->top = $top;

        // Overlay root doubles as the outside-click catcher (light scrim so the
        // panel clearly floats above the page).
        $overlay = LayoutNode::column(id: "{$this->name}:scrim");
        $overlay->spec = new ScrimSpec(alpha: 0.12);
        $overlay->child($panel);

        $this->open = true;
        $this->surface->setOverlay($overlay);
        $this->surface->onClick("{$this->name}:scrim", fn () => $this->close());
        foreach ($this->optionRows as $i => $row) {
            $this->surface->onClick($row->id, fn () => $this->select($i));
        }
        $this->surface->refreshFocusables();
    }

    /** Hide the dropdown panel. */
    public function close(): void
    {
        if (! $this->open || $this->surface === null) {
            return;
        }
        $this->open = false;
        $this->surface->setOverlay(null);
        $this->surface->refreshFocusables();
    }

    public function toggle(): void
    {
        if ($this->open) {
            $this->close();
        } else {
            $this->open();
        }
    }

    public function select(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }
        $this->selected = $index;
        $this->trigger->spec = new SelectSpec(value: $this->options[$index]);
        $this->open = false;
        $this->surface?->setOverlay(null);
        $this->surface?->refreshFocusables();

        if ($this->onSelect !== null) {
            ($this->onSelect)($index, $this->options[$index]);
        }
        $this->surface?->redraw();
    }

    /** @param callable(int,string):void $fn */
    public function onSelect(callable $fn): static
    {
        $this->onSelect = $fn;

        return $this;
    }
}
