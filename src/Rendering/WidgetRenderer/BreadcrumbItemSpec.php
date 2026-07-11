<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for one crumb in a {@see BreadcrumbSpec} trail.
 *
 * @property-read string $label  Crumb text.
 * @property-read bool   $active True for the last (current) crumb — drawn primary + bold.
 * @property-read bool   $isLast True for the final crumb (no trailing separator).
 * @property-read bool   $enabled When false the crumb is drawn muted.
 * @property-read bool   $hovered True while the pointer is over the crumb.
 */
final class BreadcrumbItemSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '',
        public readonly bool $active = false,
        public readonly bool $isLast = false,
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 5.0,
        public readonly string $separator = '/',
    ) {
    }

    public function type(): string
    {
        return 'breadcrumb_item';
    }
}
