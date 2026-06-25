<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Dialogs;

use Libui\Button;
use Libui\Entry;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Build;

/**
 * A synchronous modal input dialog built from stock libui widgets.
 *
 * Displays a title, message, a text entry field, and OK/Cancel buttons in a
 * temporary modal window, using the same nested event-loop pattern as the
 * picker dialogs. Can be called from within an already-running uiMain() loop
 * or standalone.
 *
 * ```php
 * $name = DialogPrompt::ask($mainWindow, 'Name', 'Enter your name:', 'default');
 * if ($name !== null) {
 *     // use $name
 * }
 * ```
 */
final class DialogPrompt
{
    /**
     * Show an input dialog and block until the user chooses.
     *
     * @param  Window|null  $parent      Optional parent window for positioning hints.
     * @param  string       $title       Dialog window title.
     * @param  string       $message     The prompt message to display.
     * @param  string       $defaultText Default text in the entry field.
     * @return string|null               The entered text, or null if cancelled.
     */
    public static function ask(?Window $parent, string $title, string $message, string $defaultText = ''): ?string
    {
        Ffi::init();

        $entry = new Entry();
        if ($defaultText !== '') {
            $entry->setText($defaultText);
        }

        $okButton = new Button('OK');
        $cancelButton = new Button('Cancel');

        $label = new Label($message);

        $window = Build::window($title, 360, 160,
            Build::vbox(
                $label,
                Build::stretchy($entry),
                Build::hbox(
                    Build::stretchy($okButton),
                    Build::stretchy($cancelButton),
                ),
            ),
        );

        $result = null;
        $finished = false;

        $okButton->onClicked(function () use ($entry, &$result, $window, &$finished): void {
            $result = $entry->text();
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

        while (!$finished && Ffi::get()->uiMainStep(1)) {
            // Process one platform event, then re-check $finished.
        }

        $window->destroy();

        return $result;
    }
}
