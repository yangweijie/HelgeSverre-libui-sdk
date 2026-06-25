<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Dialogs;

use Libui\Window;

/**
 * Static helper for common native dialog patterns.
 *
 * All methods require a parent Window reference and block the event loop
 * until the user dismisses the dialog (libui's native modal behavior).
 *
 * ```php
 * MessageBox::info($window, 'Saved', 'Your document has been saved.');
 * MessageBox::error($window, 'Error', 'The file could not be opened.');
 * ```
 */
final class MessageBox
{
    /**
     * Show an information dialog with a single "OK" button.
     */
    public static function info(Window $parent, string $title, string $message): void
    {
        $parent->dialogs()->msgBox($title, $message);
    }

    /**
     * Show a warning dialog (styled as a msgBox with a warning prefix).
     */
    public static function warning(Window $parent, string $title, string $message): void
    {
        $parent->dialogs()->msgBox("⚠ {$title}", $message);
    }

    /**
     * Show an error dialog with a single "OK" button.
     */
    public static function error(Window $parent, string $title, string $message): void
    {
        $parent->dialogs()->error($title, $message);
    }
}
