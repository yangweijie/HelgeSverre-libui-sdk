<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Pickers;

use Libui\Button;
use Libui\Color;
use Libui\ColorButton;
use Libui\Ffi;
use Libui\Window;
use Libui\Build;

/**
 * A synchronous modal colour picker dialog built from stock libui widgets.
 *
 * libui-ng has no native colour-picker dialog; this wraps a ColorButton in a
 * temporary modal window and runs a nested event-loop step until the user
 * confirms or cancels.
 *
 * ```php
 * $color = ColorPickerDialog::pick($mainWindow);
 * if ($color !== null) {
 *     // use $color
 * }
 * ```
 *
 * Can be called from within an already-running uiMain() event loop or
 * standalone — it spins uiMainStep(1) itself and does not call uiQuit(), so
 * the parent loop is unaffected.
 */
final class ColorPickerDialog
{
    /**
     * Open the colour picker and block until the user makes a choice.
     *
     * @param  Window|null  $parent  Optional parent window for positioning hints.
     * @return Color|null            The chosen colour, or null if cancelled.
     */
    public static function pick(?Window $parent = null): ?Color
    {
        Ffi::init();

        $colorButton = new ColorButton();
        $okButton = new Button('OK');
        $cancelButton = new Button('Cancel');

        $window = Build::window('Pick a Color', 320, 200,
            Build::vbox(
                Build::stretchy($colorButton),
                Build::hbox(
                    Build::stretchy($okButton),
                    Build::stretchy($cancelButton),
                ),
            ),
        );

        $result = null;
        $finished = false;

        $okButton->onClicked(function () use ($colorButton, &$result, $window, &$finished): void {
            $result = $colorButton->getColor();
            $finished = true;
            $window->hide();
        });

        $cancelButton->onClicked(function () use ($window, &$finished): void {
            $finished = true;
            $window->hide();
        });

        $window->onClosing(function () use (&$finished): bool {
            $finished = true;
            return true;
        });

        $window->show();

        // Blocking modal step loop — does NOT call uiQuit(), so any outer
        // uiMain() loop the caller may be inside is unaffected.
        while (!$finished && Ffi::get()->uiMainStep(1)) {
            // Process one platform event, then re-check $finished.
        }

        $window->destroy();

        return $result;
    }
}
