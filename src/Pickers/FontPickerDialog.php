<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Pickers;

use Libui\Button;
use Libui\Ffi;
use Libui\FontButton;
use Libui\Text\FontDescriptor;
use Libui\Window;
use Libui\Build;

/**
 * A synchronous modal font picker dialog built from stock libui widgets.
 *
 * libui-ng has no native font-picker dialog; this wraps a FontButton in a
 * temporary modal window and runs a nested event-loop step until the user
 * confirms or cancels.
 *
 * ```php
 * $font = FontPickerDialog::pick($mainWindow);
 * if ($font !== null) {
 *     // use $font
 * }
 * ```
 *
 * Can be called from within an already-running uiMain() event loop or
 * standalone — it spins uiMainStep(1) itself and does not call uiQuit(), so
 * the parent loop is unaffected.
 */
final class FontPickerDialog
{
    /**
     * Open the font picker and block until the user makes a choice.
     *
     * @param  Window|null  $parent  Optional parent window for positioning hints.
     * @return FontDescriptor|null   The chosen font, or null if cancelled.
     */
    public static function pick(?Window $parent = null): ?FontDescriptor
    {
        Ffi::init();

        $fontButton = new FontButton();
        $okButton = new Button('OK');
        $cancelButton = new Button('Cancel');

        $window = Build::window('Pick a Font', 360, 240,
            Build::vbox(
                Build::stretchy($fontButton),
                Build::hbox(
                    Build::stretchy($okButton),
                    Build::stretchy($cancelButton),
                ),
            ),
        );

        $result = null;
        $finished = false;

        $okButton->onClicked(function () use ($fontButton, &$result, $window, &$finished): void {
            $result = $fontButton->getFont();
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

        // Blocking modal step loop — does NOT call uiQuit(), so any outer
        // uiMain() loop the caller may be inside is unaffected.
        while (!$finished && Ffi::get()->uiMainStep(1)) {
            // Process one platform event, then re-check $finished.
        }

        $window->destroy();

        return $result;
    }
}
