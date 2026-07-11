<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Events;

use Libui\Draw\Params\AreaMouseEvent;

/**
 * High-level semantic view of a pointer interaction, decoupled from libui's raw
 * AreaMouseEvent.
 *
 * The Surface translates raw mouse events into one of these so the rest of the
 * widget layer reasons about *intent* (hover / press / release / drag), not
 * about libui's down/up/held bitfields. ENTER/LEAVE are produced by the
 * delegate's mouseCrossed callback and labelled explicitly.
 *
 * ```php
 * $p = PointerEvent::fromMouse($areaMouseEvent); // auto-classifies
 * if ($p->isPress() && $p->isLeftButton()) { ... }
 * ```
 */
final class PointerEvent
{
    public const HOVER = 'hover';
    public const DOWN = 'down';
    public const UP = 'up';
    public const MOVE = 'move';   // a button is held while the pointer moves (drag)
    public const ENTER = 'enter';
    public const LEAVE = 'leave';

    public function __construct(
        public readonly string $type,
        public readonly float $x,
        public readonly float $y,
        public readonly float $areaWidth,
        public readonly float $areaHeight,
        public readonly int $button,      // 0=none, 1=left, 2=right, 3=middle
        public readonly int $clickCount,  // 1=single, 2=double, 3=triple, ...
        public readonly int $modifiers,
        public readonly int $held,        // bitmask of buttons currently held
    ) {}

    /**
     * Classify a raw libui mouse event into a semantic PointerEvent.
     *
     * left-down -> DOWN, left-up -> UP, a held button while moving -> MOVE,
     * otherwise -> HOVER. Pass an explicit $type only for ENTER/LEAVE, which
     * libui reports through mouseCrossed rather than the mouse() callback.
     *
     * $prevHeld is the held-bitmask from the previous mouse event. Some libui
     * backends fire the press frame with held set but down left at 0, so we
     * fall back to the held-bit *transition* to detect press / drag / release
     * instead of trusting the down/up fields (which may be empty). This is what
     * makes scrollbar-thumb and slider dragging work on those builds.
     */
    public static function fromMouse(AreaMouseEvent $e, ?string $type = null, int $prevHeld = 0): self
    {
        $type ??= match (true) {
            // Explicit press / release are always authoritative when present.
            $e->down !== 0 => self::DOWN,
            $e->up !== 0 => self::UP,
            // Held-button transition: a still-held button is a drag, a held button
            // that was not held before is a press (covers down===0 press frames).
            $e->held !== 0 && $prevHeld !== 0 => self::MOVE,
            $e->held !== 0 && $prevHeld === 0 => self::DOWN,
            // A release frame may report held===0 without an explicit up bit.
            $e->held === 0 && $prevHeld !== 0 => self::UP,
            default => self::HOVER,
        };

        // Which button is involved: prefer the explicit down/up bits, otherwise
        // decode the held bitmask (1=left, 2=right, 4=middle).
        $button = match (true) {
            $e->down !== 0 => $e->down,
            $e->up !== 0 => $e->up,
            ($e->held & 1) !== 0 => 1,
            ($e->held & 2) !== 0 => 2,
            ($e->held & 4) !== 0 => 3,
            default => 0,
        };

        return new self(
            type: $type,
            x: $e->x,
            y: $e->y,
            areaWidth: $e->areaWidth,
            areaHeight: $e->areaHeight,
            button: $button,
            clickCount: $e->count,
            modifiers: $e->modifiers,
            held: $e->held,
        );
    }

    public function isLeftButton(): bool
    {
        return $this->button === 1;
    }

    /** Two or more consecutive clicks in the same spot. */
    public function isDoubleClick(): bool
    {
        return $this->clickCount >= 2;
    }

    public function isHover(): bool
    {
        return $this->type === self::HOVER;
    }

    public function isPress(): bool
    {
        return $this->type === self::DOWN;
    }

    public function isRelease(): bool
    {
        return $this->type === self::UP;
    }

    public function isDrag(): bool
    {
        return $this->type === self::MOVE;
    }

    public function isEnter(): bool
    {
        return $this->type === self::ENTER;
    }

    public function isLeave(): bool
    {
        return $this->type === self::LEAVE;
    }
}
