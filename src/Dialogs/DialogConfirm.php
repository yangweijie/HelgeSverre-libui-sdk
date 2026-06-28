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
    public static function ask(
        ?Window $parent,
        string $title,
        string $message,
    ): bool {
        Ffi::init();

        $okButton = new Button("OK");
        $cancelButton = new Button("Cancel");

        $label = new Label($message);

        [$winW, $winH] = self::calcSize($message, $parent);

        $window = Build::window(
            $title,
            $winW,
            $winH,
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

        $okButton->onClicked(function () use (
            &$result,
            $window,
            &$finished,
        ): void {
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

        if ($parent !== null) {
            $window->centeredOn($parent);
        }

        while (!$finished && Ffi::get()->uiMainStep(1)) {
            // Process one platform event, then re-check $finished.
        }

        $window->destroy();

        return $result;
    }

    /**
     * Calculate dialog window dimensions based on message length.
     *
     * @return array{int, int} [width, height]
     */
    private static function calcSize(string $message, ?Window $parent): array
    {
        // Approximate char width for default system font (~13pt)
        $charW = 7;
        // Vertical space: title bar(28) + label padding(16) + button bar(36)
        $chrome = 80;
        // Min width to fit OK + Cancel buttons side by side
        $minW = 240;

        $lines =
            mb_strlen($message) > 0
                ? max(1, (int) ceil((mb_strlen($message) * $charW) / 280))
                : 1;

        $labelH = max(20, $lines * 20);
        $height = $chrome + $labelH;

        $width = max($minW, 280);

        if ($parent !== null) {
            [$pw] = $parent->getContentSize();
            // Cap at 80% of parent width, minimum 200
            $width = max(200, min($width, (int) ($pw * 0.8)));
        }

        return [$width, $height];
    }
}
