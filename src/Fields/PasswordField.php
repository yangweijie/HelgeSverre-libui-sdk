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
 * A labelled password input — Label + PasswordEntry in a horizontal row.
 *
 * The entered text is masked on screen but readable programmatically.
 *
 * ```php
 * $pw = new PasswordField('Password:');
 * // later: $pw->value() returns the plain-text password
 * ```
 *
 * @implements HasValue<string>
 */
class PasswordField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Entry $entry;
    private readonly Box $box;

    public function __construct(string $labelText, string $initialValue = '')
    {
        $this->label = new Label($labelText);
        $this->entry = Entry::password();
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
