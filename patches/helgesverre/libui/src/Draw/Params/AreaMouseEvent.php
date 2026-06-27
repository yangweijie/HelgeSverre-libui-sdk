<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

use Libui\Generated\Flags\Modifiers;

/** A PHP view of uiAreaMouseEvent. */
final class AreaMouseEvent
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        public readonly int $down, // button pressed this event (1=left, 2=right, 3=middle, 0=none)
        // NOTE: On some Windows systems, right-click reports as down=3 instead of down=2.
        // Use ($event->down === 2 || $event->down === 3) to reliably detect right-click.
        public readonly int $up, // button released this event (1=left, 2=right, 3=middle, 0=none)
        public readonly int $count, // click count (1 = single, 2 = double)
        public readonly int $modifiers, // bitmask, see Generated\Flags\Modifiers
        public readonly int $held, // bitmask of buttons 1..64 currently held
    ) {}

    public static function fromCData(\FFI\CData $e): self
    {
        return new self(
            $e->X,
            $e->Y,
            $e->AreaWidth,
            $e->AreaHeight,
            $e->Down,
            $e->Up,
            $e->Count,
            $e->Modifiers,
            $e->Held1To64,
        );
    }

    /**
     * Whether the left mouse button triggered this event.
     *
     * In libui-ng, Down/Up values are: 1 = left, 2 = right, 3 = middle, 0 = none.
     */
    public function isLeftButtonDown(): bool
    {
        return $this->down === 1;
    }

    /**
     * Whether the right mouse button triggered this event.
     */
    public function isRightButtonDown(): bool
    {
        return $this->down === 2;
    }

    /**
     * Whether the Shift key was held during this event.
     */
    public function isShiftHeld(): bool
    {
        return ($this->modifiers & Modifiers::Shift) !== 0;
    }

    /**
     * Whether the Ctrl key was held during this event.
     */
    public function isCtrlHeld(): bool
    {
        return ($this->modifiers & Modifiers::Ctrl) !== 0;
    }

    /**
     * Whether this event is a double-click (count === 2).
     */
    public function isDoubleClick(): bool
    {
        return $this->count === 2;
    }
}
