<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for a modal dialog's card surface.
 *
 * Drawn as the background of the dialog: a {@see DesignTokens} surface fill with
 * a hairline border and a generous corner radius. The title / message and the
 * action buttons are separate nodes layered on top (see DialogBodySpec and the
 * ButtonSpec leaves the {@see \Yangweijie\Ui2\Widgets\DialogControl} builds), so
 * the card itself is a pure container background.
 *
 * @property-read float $radius Corner radius of the card.
 */
final class DialogCardSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $radius = 14.0,
    ) {
    }

    public function type(): string
    {
        return 'dialog_card';
    }
}
