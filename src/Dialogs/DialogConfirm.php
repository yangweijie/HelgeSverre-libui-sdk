<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Dialogs;

use Libui\Button;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Build;

/**
 * A synchronous modal confirmation dialog built from stock libui widgets.
 *
 * Displays a title, message, and OK/Cancel buttons in a temporary modal window,
 * using the same nested event-loop pattern as the picker dialogs. Can be called
 * from within an already-running uiMain() loop or standalone.
 *
 * ```php
 * $confirmed = DialogConfirm::ask($mainWindow, 'Delete', 'Are you sure?');
 * if ($confirmed) {
 *     // perform deletion
 * }
 * ```
 */
final class DialogConfirm
{
    /**
     * Show a confirmation dialog and block until the user chooses.
     *
     * @param  Window|null  $parent   Optional parent window for positioning hints.
     * @param  string       $title    Dialog window title.
     * @param  string       $message  The confirmation message to display.
     * @return bool                   True if the user clicked OK, false if cancelled.
     */
    public static function ask(?Window $parent, string $title, string $message): bool
    {
        Ffi::init();

        $okButton = new Button('OK');
        $cancelButton = new Button('Cancel');

        // A short label with wrapping; enforce min height so the window has body.
        $label = new Label($message);

        $window = Build::window($title, 360, 140,
            Build::vbox(
                Build::stretchy($label),
                Build::hbox(
                    Build::stretchy($okButton),
                    Build::stretchy($cancelButton),
                ),
            ),
        );

        $result = false;
        $finished = false;

        $okButton->onClicked(function () use (&$result, $window, &$finished): void {
            $result = true;
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
