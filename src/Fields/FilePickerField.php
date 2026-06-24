<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Button;
use Libui\Control;
use Libui\Dialogs;
use Libui\Entry;
use Libui\Window;
use Yangweijie\Ui2\Composite;
use Yangweijie\Ui2\EmitsEvents;

/**
 * A file picker composite: read-only Entry + "Browse…" button.
 *
 * Opens the native file-open dialog when the button is clicked.
 *
 *     $form->append(new FilePickerField($window, 'Select a file:'));
 *
 * @implements HasValue<?string>
 */
class FilePickerField extends Composite
{
    use EmitsEvents;

    private readonly Entry $entry;
    private readonly Button $button;
    private readonly Box $box;

    public function __construct(
        private readonly Window $parent,
        string $buttonLabel = 'Browse…',
    ) {
        $this->entry = new Entry();
        $this->entry->setReadOnly(true);

        $this->button = new Button($buttonLabel);
        $this->button->onClicked(function (): void {
            $path = (new Dialogs($this->parent))->openFile();
            if ($path !== null) {
                $this->entry->setText($path);
                $this->emit('change', $path);
            }
        });

        $this->box = Box::horizontal();
        $this->box->append($this->entry, stretchy: true);
        $this->box->append($this->button);
    }

    public function root(): Control
    {
        return $this->box;
    }

    /** The current file path, or '' if none selected. */
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
