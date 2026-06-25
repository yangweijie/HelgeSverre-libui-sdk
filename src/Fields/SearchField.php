<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Entry;
use Libui\Label;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled search input — Label + SearchEntry in a horizontal row.
 *
 * The underlying SearchEntry may debounce change callbacks on some platforms
 * (macOS), which means the 'change' event may fire after a short delay rather
 * than on every keystroke.
 *
 * ```php
 * $search = new SearchField('Find:', '');
 * $search->on('change', fn (string $q) => filterResults($q));
 * ```
 *
 * @implements HasValue<string>
 */
class SearchField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Entry $entry;
    private readonly Box $box;

    public function __construct(string $labelText, string $initialValue = '')
    {
        $this->label = new Label($labelText);
        $this->entry = Entry::search();
        $this->entry->setText($initialValue);

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->appendStretchy($this->entry);

        $this->entry->onChanged(function (): void {
            $this->emit('change', $this->value());
        });
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): string
    {
        return $this->entry->text();
    }

    public function setValue(mixed $value): static
    {
        $this->entry->setText((string) $value);
        return $this;
    }
}
