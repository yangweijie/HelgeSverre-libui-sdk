<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Pickers;

use Libui\Dialogs;
use Libui\Window;

/**
 * A synchronous native file-open dialog.
 *
 * Unlike the other *PickerDialog classes this does not need a nested modal
 * window: libui's Dialogs::openFile() is itself a blocking native modal, so we
 * just forward to it (mirroring how {@see \Yangweijie\Ui2\Fields\FilePickerField}
 * works). Call from within an already-running uiMain() loop or standalone.
 *
 * ```php
 * $path = FilePickerDialog::pick($mainWindow);
 * if ($path !== null) {
 *     // use $path
 * }
 * ```
 */
final class FilePickerDialog
{
    /**
     * Open the native file picker and block until the user chooses or cancels.
     *
     * @param  Window      $parent Required parent window for proper modality.
     * @return string|null         The chosen path, or null if cancelled.
     */
    public static function pick(Window $parent): ?string
    {
        $path = (new Dialogs($parent))->openFile();

        return ($path === '' || $path === null) ? null : $path;
    }
}
