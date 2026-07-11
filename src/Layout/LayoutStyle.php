<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

/**
 * Flexbox layout style — a CSS-flbox subset expressed as struct fields.
 *
 * This is the "how to lay out" half of the layout layer. It deliberately
 * mirrors the flexbox model (direction / gap / padding / justify / align /
 * grow / shrink / basis / fixed size) so the behaviour is familiar, but it is
 * consumed by {@see FlexLayout} as plain PHP fields rather than parsed CSS.
 *
 * The object is immutable; build a new one (or use {@see with()}) to change
 * style. A node with the default style is a non-stretching, auto-sized leaf.
 */
final class LayoutStyle
{
    public const ROW = 'row';
    public const COLUMN = 'column';

    public const JUSTIFY_START = 'start';
    public const JUSTIFY_CENTER = 'center';
    public const JUSTIFY_END = 'end';
    public const JUSTIFY_SPACE_BETWEEN = 'spaceBetween';
    public const JUSTIFY_SPACE_AROUND = 'spaceAround';
    public const JUSTIFY_SPACE_EVENLY = 'spaceEvenly';

    public const ALIGN_START = 'start';
    public const ALIGN_CENTER = 'center';
    public const ALIGN_END = 'end';
    public const ALIGN_STRETCH = 'stretch';

    public function __construct(
        /** Main-axis direction: row (horizontal) or column (vertical). */
        public string $direction = self::ROW,
        /** Spacing between children along the main axis (px). */
        public float $gap = 0.0,
        /** Uniform inner padding of the container (px). */
        public float $padding = 0.0,
        /** Main-axis distribution of leftover space when no child grows. */
        public string $justify = self::JUSTIFY_START,
        /** Cross-axis alignment of children. */
        public string $align = self::ALIGN_STRETCH,
        /** Grow factor: shares leftover main-axis space proportionally. */
        public float $grow = 0.0,
        /** Shrink factor: how readily this child gives back space on overflow. */
        public float $shrink = 1.0,
        /** Flex-basis: starting main-axis size before grow/shrink (null = auto). */
        public ?float $basis = null,
        /** Fixed width override (wins over layout for the horizontal axis). */
        public ?float $width = null,
        /** Fixed height override (wins over layout for the vertical axis). */
        public ?float $height = null,
        /**
         * Absolute positioning: when true, this child is taken out of the flex
         * flow and placed at (contentX + left, contentY + top) with its own
         * width/height. Used for overlays (dropdowns / popovers) that must sit
         * at an exact point regardless of sibling layout.
         */
        public bool $absolute = false,
        /** Left offset (px) from the container's content origin (absolute mode). */
        public float $left = 0.0,
        /** Top offset (px) from the container's content origin (absolute mode). */
        public float $top = 0.0,
    ) {
    }

    /** True when this style describes a container (row/column) rather than a leaf. */
    public function isColumn(): bool
    {
        return $this->direction === self::COLUMN;
    }

    /**
     * Return a copy with the given fields overridden — handy for declaring
     * styles inline without rewriting every constructor argument.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        $clone = clone $this;
        foreach ($overrides as $key => $value) {
            if (property_exists($clone, $key)) {
                $clone->$key = $value;
            }
        }

        return $clone;
    }
}
