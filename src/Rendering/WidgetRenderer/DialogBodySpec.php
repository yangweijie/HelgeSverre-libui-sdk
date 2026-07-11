<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a modal dialog's title + message block.
 *
 * @property-read string $title   Bold-ish heading at the top of the dialog.
 * @property-read string $message Wrapped body text below the title.
 */
final class DialogBodySpec extends WidgetSpec
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $message = '',
    ) {
    }

    public function type(): string
    {
        return 'dialog_body';
    }
}
