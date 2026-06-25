<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Label;
use Libui\RadioButtons;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled radio button group — Label + RadioButtons in a horizontal row.
 *
 * Options are added by name after construction:
 *
 * ```php
 * $lang = new RadioGroup('Language:');
 * $lang->addOptions(['PHP', 'Python', 'Rust']);
 * $lang->on('change', fn (int $idx) => print("Selected: $idx"));
 * ```
 *
 * @implements HasValue<int>
 */
class RadioGroup extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly RadioButtons $radioButtons;
    private readonly Box $box;

    public function __construct(string $labelText)
    {
        $this->label = new Label($labelText);
        $this->radioButtons = new RadioButtons();

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->radioButtons);

        $this->radioButtons->onSelected(function (): void {
            $this->emit('change', $this->value());
        });
    }

    /**
     * Add multiple options at once.
     *
     * @param list<string> $options
     * @return $this
     */
    public function addOptions(array $options): static
    {
        foreach ($options as $option) {
            $this->radioButtons->append($option);
        }
        return $this;
    }

    /**
     * Add a single option.
     *
     * @return $this
     */
    public function addOption(string $label): static
    {
        $this->radioButtons->append($label);
        return $this;
    }

    public function root(): Control
    {
        return $this->box;
    }

    /**
     * The index of the selected radio button (0-based), or -1 if none.
     */
    public function value(): int
    {
        return $this->radioButtons->selected();
    }

    /**
     * Select the radio button at the given index.
     */
    public function setValue(mixed $value): static
    {
        $this->radioButtons->setSelected((int) $value);
        return $this;
    }
}
