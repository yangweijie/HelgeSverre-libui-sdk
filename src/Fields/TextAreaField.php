<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Label;
use Libui\MultilineEntry;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled multi-line text area — Label + MultilineEntry in a vertical column.
 *
 * ```php
 * $bio = new TextAreaField('Biography:');
 * $bio->on('change', fn (string $val) => print("Bio updated\n"));
 * ```
 *
 * @implements HasValue<string>
 */
class TextAreaField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly MultilineEntry $entry;
    private readonly Box $box;

    public function __construct(string $labelText, string $initialValue = '')
    {
        $this->label = new Label($labelText);
        $this->entry = new MultilineEntry();
        $this->entry->setText($initialValue);

        // Vertical stacking: label on top, text area stretches below
        $this->box = new Box();
        $this->box->setPadded(true);
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
