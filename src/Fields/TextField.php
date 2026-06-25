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
 * A labelled text input — Label + Entry in a horizontal row.
 *
 * The field bridges the upstream Entry::onChanged callback into the Composite
 * event system so callers can subscribe to 'change' via ->on('change', fn).
 *
 * ```php
 * $name = new TextField('Name:', 'John Doe');
 * $name->on('change', fn (string $val) => print("Hello, $val!"));
 * ```
 *
 * @implements HasValue<string>
 */
class TextField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Entry $entry;
    private readonly Box $box;

    public function __construct(string $labelText, string $initialValue = '')
    {
        $this->label = new Label($labelText);
        $this->entry = new Entry();
        $this->entry->setText($initialValue);

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->appendStretchy($this->entry);

        // Bridge upstream change event → Composite 'change' event
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
