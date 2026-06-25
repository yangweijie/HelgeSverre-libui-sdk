<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Combobox;
use Libui\Control;
use Libui\Label;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A labelled combo box (read-only dropdown) — Label + Combobox in a horizontal row.
 *
 * ```php
 * $role = new ComboBoxField('Role:');
 * $role->addOptions(['Admin', 'Editor', 'Viewer']);
 * $role->on('change', fn (int $idx) => print("Selected index: $idx"));
 * ```
 *
 * @implements HasValue<int>
 */
class ComboBoxField extends Composite
{
    use EmitsEvents;

    private readonly Label $label;
    private readonly Combobox $combobox;
    private readonly Box $box;

    public function __construct(string $labelText)
    {
        $this->label = new Label($labelText);
        $this->combobox = new Combobox();

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->combobox);

        $this->combobox->onSelected(function (): void {
            $this->emit('change', $this->value());
        });
    }

    /**
     * Add multiple items at once.
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
     * Add a single item.
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

    /**
     * The index of the selected item (0-based), or -1 if none.
     */
    public function value(): int
    {
        return $this->combobox->selected();
    }

    /**
     * Select the item at the given index.
     */
    public function setValue(mixed $value): static
    {
        $this->combobox->setSelected((int) $value);
        return $this;
    }
}
