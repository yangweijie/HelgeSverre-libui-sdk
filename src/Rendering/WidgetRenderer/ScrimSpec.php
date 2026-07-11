<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * A transparent / lightly-dimmed full-area scrim used behind popovers and
 * dropdown panels. Carries no interactive semantics of its own — it simply
 * paints a flat fill so an overlay reads as floating above the page, and (by
 * owning an id) acts as the outside-click catcher that dismisses the overlay.
 */
final class ScrimSpec extends WidgetSpec
{
    public function __construct(
        public readonly float $alpha = 0.0,
    ) {
    }

    public function type(): string
    {
        return 'scrim';
    }
}
