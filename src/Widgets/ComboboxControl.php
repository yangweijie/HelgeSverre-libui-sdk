<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PanelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ScrimSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;

/**
 * A self-drawn combobox (.combobox), wired into a {@see Surface}: an editable
 * {@see TextFieldSpec} plus a caret button that toggles a DROPDOWN PANEL.
 *
 * The panel is shown as a Surface overlay anchored *below* the field — it
 * floats over the other widgets (covering them) instead of pushing the layout
 * around, and dismisses when you click anywhere outside it. Typing still edits
 * the field via the Surface text-input path; picking an option fills it.
 *
 * ```php
 * $combo = new ComboboxControl('lang', ['PHP', 'Rust', 'Go'], value: 'PHP');
 * $tree->child($combo->root());
 * $combo->bind($surface)->onChange(fn ($v) => setLang($v));
 * ```
 */
final class ComboboxControl
{
    private LayoutNode $root;

    private LayoutNode $field;

    private LayoutNode $caret;

    /** @var list<LayoutNode> */
    private array $optionRows = [];

    /** @var list<string> */
    private array $options;

    private bool $open = false;

    private ?Surface $surface = null;

    /** @var callable(string):void|null */
    private $onChange = null;

    /**
     * @param list<string> $options
     */
    public function __construct(
        private readonly string $name,
        array $options,
        string $value = '',
        private float $width = 220.0,
        private float $height = 34.0,
        private float $rowHeight = 32.0,
    ) {
        $this->options = array_values($options);

        $this->field = LayoutNode::leaf(
            "{$this->name}:input",
            new TextFieldSpec(value: $value, placeholder: 'Select or type…'),
            width: $this->width - 34,
            height: $this->height,
        );
        $this->caret = LayoutNode::leaf(
            "{$this->name}:caret",
            new ButtonSpec('▾', 'soft'),
            width: 30,
            height: $this->height,
        );

        $bar = LayoutNode::row(gap: 4, align: LayoutStyle::ALIGN_CENTER, id: "{$this->name}:bar", height: $this->height)
            ->child($this->field)
            ->child($this->caret);
        $this->root = LayoutNode::column(gap: 0, id: $this->name)
            ->child($bar);
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

    public function value(): string
    {
        return $this->field->spec instanceof TextFieldSpec ? $this->field->spec->value : '';
    }

    /** Register input + caret handlers on a Surface. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;

        $surface->onText($this->field->id, function (string $char, bool $backspace): void {
            $cur = $this->value();
            $next = $backspace ? mb_substr($cur, 0, -1) : $cur . $char;
            $this->setValue($next, false);
        });

        $surface->onClick($this->caret->id, fn () => $this->toggle());

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
                new ListRowSpec(label: $label),
                height: $this->rowHeight,
            );
            $this->optionRows[] = $row;
            $panel->child($row);
        }

        return $panel;
    }

    /** Show the dropdown panel as an overlay anchored below the field. */
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
        // Flip above the field when there isn't room below.
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
            $this->surface->onClick($row->id, fn () => $this->pick($i));
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

    /** Replace the field value (and optionally fire onChange). */
    public function setValue(string $value, bool $fire = true): void
    {
        $this->field->spec = new TextFieldSpec(
            value: $value,
            placeholder: $this->field->spec instanceof TextFieldSpec ? $this->field->spec->placeholder : '',
        );
        if ($fire && $this->onChange !== null) {
            ($this->onChange)($value);
        }
        $this->surface?->redraw();
    }

    /** Programmatically choose an option (fills the field, collapses the panel). */
    public function pick(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }
        $this->setValue($this->options[$index]);
        $this->close();
    }

    /** @param callable(string):void $fn */
    public function onChange(callable $fn): static
    {
        $this->onChange = $fn;

        return $this;
    }
}
