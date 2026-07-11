<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

/**
 * A single grid track (column or row) sizing definition.
 *
 * Mirrors CSS grid track sizing with three flavours:
 *  - px:  a fixed pixel size
 *  - fr:  a fraction of the leftover space (1fr, 2fr, …)
 *  - auto: sized to content (resolved as 0 in v1 — no content measurement yet)
 */
final class GridTrack
{
    public const FR = 'fr';
    public const PX = 'px';
    public const AUTO = 'auto';

    public function __construct(
        public string $type,
        public float $value = 1.0,
    ) {
    }

    public static function fr(float $value = 1.0): self
    {
        return new self(self::FR, $value);
    }

    public static function px(float $value): self
    {
        return new self(self::PX, $value);
    }

    public static function auto(): self
    {
        return new self(self::AUTO);
    }
}
