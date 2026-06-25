<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Checkbox;
use Libui\Control;
use Libui\Label;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled checkbox — Label + Checkbox in a horizontal row.
 *
 * ```php
 * $terms = new CheckboxField('I agree to the terms', false);
 * $terms->on('change', fn (bool $val) => print($val ? 'Agreed' : 'Not agreed'));
 * ```
 *
 * @implements HasValue<bool>
 */
class CheckboxField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Checkbox $checkbox;
    private readonly Box $box;

    public function __construct(string $labelText, bool $initialValue = false)
    {
        $this->label = new Label($labelText);
        $this->checkbox = new Checkbox('');
        $this->checkbox->setChecked($initialValue);

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->checkbox);

        $this->checkbox->onToggled(function (): void {
            $this->emit('change', $this->value());
        });
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): bool
    {
        return $this->checkbox->checked();
    }

    public function setValue(mixed $value): static
    {
        $this->checkbox->setChecked((bool) $value);
        return $this;
    }
}
