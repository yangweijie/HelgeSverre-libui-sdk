<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\EditableCombobox;
use Libui\Label;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled editable combo box — Label + EditableCombobox in a horizontal row.
 *
 * Unlike ComboBoxField, the user can type arbitrary text.
 *
 * ```php
 * $city = new EditableComboBoxField('City:');
 * $city->addOptions(['Beijing', 'Shanghai', 'Shenzhen']);
 * $city->on('change', fn (string $val) => print("City: $val"));
 * ```
 *
 * @implements HasValue<string>
 */
class EditableComboBoxField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly EditableCombobox $combobox;
    private readonly Box $box;

    public function __construct(string $labelText)
    {
        $this->label = new Label($labelText);
        $this->combobox = new EditableCombobox();

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->combobox);

        $this->combobox->onChanged(function (): void {
            $this->emit('change', $this->value());
        });
    }

    /**
     * Add multiple preset items at once.
     *
     * @param list<string> $items
     * @return $this
     */
    public function addOptions(array $items): static
    {
        foreach ($items as $item) {
            $this->combobox->append($item);
        }
        return $this;
    }

    /**
     * Add a single preset item.
     *
     * @return $this
     */
    public function addOption(string $label): static
    {
        $this->combobox->append($label);
        return $this;
    }

    public function root(): Control
    {
        return $this->box;
    }

    public function value(): string
    {
        return $this->combobox->text();
    }

    public function setValue(mixed $value): static
    {
        $this->combobox->setText((string) $value);
        return $this;
    }
}
