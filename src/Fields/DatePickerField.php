<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\DateTimePicker;
use Libui\Label;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled date/time picker — Label + DateTimePicker in a horizontal row.
 *
 * Creates a full date+time picker by default. Use the static factory for
 * date-only or time-only variants:
 *
 * ```php
 * $date = new DatePickerField('Appointment:');           // date + time
 * $date = DatePickerField::dateOnly('Date:');            // date only
 * $date = DatePickerField::timeOnly('Time:');            // time only
 * $date->on('change', fn (\DateTimeImmutable $dt) => …);
 * ```
 *
 * @implements HasValue<\DateTimeImmutable>
 */
class DatePickerField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly DateTimePicker $picker;
    private readonly Box $box;

    public function __construct(string $labelText, ?DateTimePicker $picker = null)
    {
        $this->label = new Label($labelText);
        $this->picker = $picker ?? new DateTimePicker();

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->picker);

        $this->picker->onChanged(function (): void {
            $this->emit('change', $this->value());
        });
    }

    /**
     * Create a date-only picker field.
     */
    public static function dateOnly(string $labelText): self
    {
        return new self($labelText, DateTimePicker::dateOnly());
    }

    /**
     * Create a time-only picker field.
     */
    public static function timeOnly(string $labelText): self
    {
        return new self($labelText, DateTimePicker::timeOnly());
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): \DateTimeImmutable
    {
        return $this->picker->getValue();
    }

    /**
     * @param \DateTimeInterface $value
     */
    public function setValue(mixed $value): static
    {
        $this->picker->setValue($value);
        return $this;
    }
}
