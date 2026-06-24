<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Label;
use Libui\Slider;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labeled slider with a live value readout.
 *
 *     $volume = new SliderField('Volume:', 0, 100);
 *     $volume->on('change', fn (int $val) => print("Volume: $val"));
 *
 * @implements HasValue<int>
 */
class SliderField extends Composite
{
    use EmitsEvents;

    private readonly Slider $slider;
    private readonly Label $valueLabel;
    private readonly Box $box;

    public function __construct(
        string $labelText,
        int $min,
        int $max,
    ) {
        $this->slider = new Slider($min, $max);
        $this->valueLabel = new Label('');

        $label = new Label($labelText);
        $this->box = Box::horizontal();
        $this->box->append($label);
        $this->box->append($this->slider, stretchy: true);
        $this->box->append($this->valueLabel);

        $this->syncLabel();

        $this->slider->onChanged(function (): void {
            $this->syncLabel();
            $this->emit('change', $this->slider->value());
        });

        $this->slider->onReleased(function (): void {
            $this->syncLabel();
        });
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): int
    {
        return $this->slider->value();
    }

    public function setValue(mixed $value): static
    {
        $this->slider->setValue((int) $value);
        $this->syncLabel();
        return $this;
    }

    private function syncLabel(): void
    {
        $this->valueLabel->setText((string) $this->slider->value());
    }
}
