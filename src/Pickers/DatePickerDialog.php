<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Pickers;

use Libui\Button;
use Libui\DateTimePicker;
use Libui\Ffi;
use Libui\Window;
use Libui\Build;

/**
 * A synchronous modal date picker dialog built from stock libui widgets.
 *
 * Wraps a DateTimePicker (in date-only mode) in a temporary modal window with
 * OK/Cancel buttons, using the same nested event-loop pattern as ColourPickerDialog
 * and FontPickerDialog. Can be called from within an already-running uiMain() loop
 * or standalone.
 *
 * ```php
 * $date = DatePickerDialog::pick($mainWindow);
 * if ($date !== null) {
 *     // use $date
 * }
 * ```
 */
final class DatePickerDialog
{
    /**
     * Open the date picker and block until the user makes a choice.
     *
     * @param  Window|null           $parent  Optional parent window for positioning hints.
     * @return \DateTimeImmutable|null        The chosen date, or null if cancelled.
     */
    public static function pick(?Window $parent = null): ?\DateTimeImmutable
    {
        Ffi::init();

        $picker = DateTimePicker::dateOnly();
        $okButton = new Button('OK');
        $cancelButton = new Button('Cancel');

        $window = Build::window('Pick a Date', 320, 160,
            Build::vbox(
                Build::stretchy($picker),
                Build::hbox(
                    Build::stretchy($okButton),
                    Build::stretchy($cancelButton),
                ),
            ),
        );

        $result = null;
        $finished = false;

        $okButton->onClicked(function () use ($picker, &$result, $window, &$finished): void {
            $result = $picker->getValue();
            $finished = true;
            $window->hide();
        });

        $cancelButton->onClicked(function () use ($window, &$finished): void {
            $finished = true;
            $window->hide();
        });

        $window->onClosing(function () use ($window, &$finished): bool {
            $finished = true;
            $window->hide();
            return false;
        });

        $window->show();

        if ($parent !== null) {
            $window->centeredOn($parent);
        }

        while (!$finished && Ffi::get()->uiMainStep(1)) {
            // Process one platform event, then re-check $finished.
        }

        $window->destroy();

        return $result;
    }
}
