<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Events;

use Libui\Draw\Params\AreaKeyEvent;
use Libui\Generated\Enum\ExtKey;

/**
 * Semantic keyboard event.
 *
 * On this libui build, *printable* keys (including Tab / Enter / Space) arrive
 * via the ASCII `$key` field, while arrow / function keys arrive via `$extKey`.
 * We expose intent helpers for both so the Surface can drive focus navigation
 * (Tab / Shift+Tab) and widget activation (Enter / Space) without caring which
 * channel a given key uses.
 *
 * ```php
 * $k = KeyboardEvent::fromKey($areaKeyEvent);
 * if ($k->isShiftTab()) { $focus->focusPrev(); }
 * elseif ($k->isTab()) { $focus->focusNext(); }
 * elseif (($k->isEnter() || $k->isSpace()) && $focus->current() !== null) { ... }
 * ```
 */
final class KeyboardEvent
{
    public const DOWN = 'down';
    public const UP = 'up';

    // Modifiers flag bits (Libui\Generated\Flags\Modifiers).
    private const SHIFT = 4;

    public function __construct(
        public readonly string $type,
        public readonly int $key,        // ASCII code, or 0 for an extended key
        public readonly int $extKey,     // ExtKey value, or 0 for an ASCII key
        public readonly int $modifiers,  // bitmask of held modifiers
        public readonly string $char,    // the typed character, or '' for ext keys
    ) {}

    public static function fromKey(AreaKeyEvent $e): self
    {
        return new self(
            type: $e->up ? self::UP : self::DOWN,
            key: $e->key,
            extKey: $e->extKey,
            modifiers: $e->modifiers,
            char: $e->char(),
        );
    }

    public function isEnter(): bool
    {
        return $this->key === 13 || $this->char === "\r" || $this->char === "\n";
    }

    public function isTab(): bool
    {
        return $this->key === 9 || $this->char === "\t";
    }

    public function isSpace(): bool
    {
        return $this->key === 32 || $this->char === ' ';
    }

    public function isEscape(): bool
    {
        return $this->key === 27 || $this->char === "\x1b";
    }

    /** True for a printable character key (excludes control/ext keys). */
    public function isPrintable(): bool
    {
        if ($this->key === 0 && $this->char === '') {
            return false;
        }

        return $this->char !== '' && ord($this->char) >= 32 && ord($this->char) !== 127;
    }

    /** True for the Backspace / Delete key. */
    public function isBackspace(): bool
    {
        return $this->key === 8 || $this->char === "\x7f";
    }

    public function isShift(): bool
    {
        return ($this->modifiers & self::SHIFT) !== 0;
    }

    public function isShiftTab(): bool
    {
        return $this->isTab() && $this->isShift();
    }

    public function isArrowUp(): bool
    {
        return $this->extKey === ExtKey::Up->value;
    }

    public function isArrowDown(): bool
    {
        return $this->extKey === ExtKey::Down->value;
    }

    public function isArrowLeft(): bool
    {
        return $this->extKey === ExtKey::Left->value;
    }

    public function isArrowRight(): bool
    {
        return $this->extKey === ExtKey::Right->value;
    }

    /** True for key-down (press) events; false for key-up (release). */
    public function isPressed(): bool
    {
        return $this->type === self::DOWN;
    }
}
