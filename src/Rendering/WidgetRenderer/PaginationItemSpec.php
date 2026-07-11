<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Visual state for one page token in a {@see PaginationSpec} strip.
 *
 * @property-read string $label  Page number, or a glyph for prev/next/gap.
 * @property-read bool   $active True for the current page (filled primary).
 * @property-read string $kind   One of 'page' | 'prev' | 'next' | 'gap'.
 * @property-read bool   $enabled When false (e.g. prev on page 1) the token is muted.
 * @property-read bool   $hovered True while the pointer is over the token.
 */
final class PaginationItemSpec extends WidgetSpec
{
    public function __construct(
        public readonly string $label = '1',
        public readonly bool $active = false,
        public readonly string $kind = 'page',
        public readonly bool $enabled = true,
        public readonly bool $hovered = false,
        public readonly float $radius = 6.0,
    ) {
    }

    public function type(): string
    {
        return 'pagination_item';
    }
}
