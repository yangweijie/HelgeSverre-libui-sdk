<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SearchFieldSpec;

/**
 * A self-drawn search field (.search_field), wired into a {@see Surface}.
 *
 * Builds a row of a {@see SearchFieldSpec} input leaf plus a clear ("×") button
 * leaf shown when there is text. Typing is routed through the Surface's text-
 * input path (click-to-focus then type); the clear button empties the value.
 *
 * ```php
 * $search = new SearchFieldControl('q', placeholder: 'Search files…');
 * $tree->child($search->root());
 * $search->bind($surface)->onChange(fn ($v) => filter($v));
 * ```
 */
final class SearchFieldControl
{
    private LayoutNode $root;

    private LayoutNode $field;

    private LayoutNode $clear;

    private ?Surface $surface = null;

    /** @var callable(string):void|null */
    private $onChange = null;

    public function __construct(
        private readonly string $name,
        string $value = '',
        float $width = 220.0,
        float $height = 34.0,
        string $placeholder = 'Search…',
    ) {
        $this->field = LayoutNode::leaf(
            "{$this->name}:input",
            new SearchFieldSpec(value: $value, placeholder: $placeholder),
            width: $width - 34,
            height: $height,
        );
        $this->clear = LayoutNode::leaf(
            "{$this->name}:clear",
            new ButtonSpec($value === '' ? '×' : '×', 'soft', enabled: $value !== ''),
            width: 30,
            height: $height,
        );
        $this->root = LayoutNode::row(gap: 4, align: LayoutStyle::ALIGN_CENTER, id: $this->name)
            ->child($this->field)
            ->child($this->clear);
    }

    /** The control's root node — drop this into a Surface tree. */
    public function root(): LayoutNode
    {
        return $this->root;
    }

    public function value(): string
    {
        return $this->field->spec instanceof SearchFieldSpec ? $this->field->spec->value : '';
    }

    /** Register input + clear handlers on a Surface and keep it for repaints. */
    public function bind(Surface $surface): static
    {
        $this->surface = $surface;

        $surface->onText($this->field->id, function (string $char, bool $backspace): void {
            $cur = $this->value();
            $next = $backspace ? mb_substr($cur, 0, -1) : $cur . $char;
            $this->setValue($next);
        });

        $surface->onClick($this->clear->id, fn () => $this->setValue(''));

        return $this;
    }

    /** Replace the field's text, refresh the clear button, and repaint. */
    public function setValue(string $value): void
    {
        $this->field->spec = new SearchFieldSpec(
            value: $value,
            placeholder: $this->field->spec instanceof SearchFieldSpec ? $this->field->spec->placeholder : 'Search…',
        );
        $this->clear->spec = new ButtonSpec('×', 'soft', enabled: $value !== '');

        if ($this->onChange !== null) {
            ($this->onChange)($value);
        }
        $this->surface?->redraw();
    }

    /** @param callable(string):void $fn */
    public function onChange(callable $fn): static
    {
        $this->onChange = $fn;

        return $this;
    }
}
