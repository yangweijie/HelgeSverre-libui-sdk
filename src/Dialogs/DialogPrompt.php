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

        [$winW, $winH] = self::calcSize($message, $parent);

        $window = Build::window($title, $winW, $winH,
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
        $charW = 7;
        // title(28) + label(20) + entry(28) + button bar(36) + margins(28)
        $chrome = 140;
        $minW = 240;

        $lines = mb_strlen($message) > 0
            ? max(1, (int) ceil(mb_strlen($message) * $charW / 280))
            : 1;

        $labelH = max(20, $lines * 20);
        $height = $chrome + $labelH;

        $width = max($minW, 280);

        if ($parent !== null) {
            [$pw] = $parent->getContentSize();
            $width = max(200, min($width, (int) ($pw * 0.8)));
        }

        return [$width, $height];
    }
}
