<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Label;
use Libui\Spinbox;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled numeric input — Label + Spinbox in a horizontal row.
 *
 * ```php
 * $age = new NumberField('Age:', 0, 150, 25);
 * $age->on('change', fn (int $val) => print("Age: $val"));
 * ```
 *
 * @implements HasValue<int>
 */
class NumberField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Spinbox $spinbox;
    private readonly Box $box;

    public function __construct(string $labelText, int $min, int $max, int $initialValue = 0)
    {
        $this->label = new Label($labelText);
        $this->spinbox = new Spinbox($min, $max);
        $this->spinbox->setValue($initialValue);

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->appendStretchy($this->spinbox);

        $this->spinbox->onChanged(function (): void {
            $this->emit('change', $this->value());
        });
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): int
    {
        return $this->spinbox->value();
    }

    public function setValue(mixed $value): static
    {
        $this->spinbox->setValue((int) $value);
        return $this;
    }
}
