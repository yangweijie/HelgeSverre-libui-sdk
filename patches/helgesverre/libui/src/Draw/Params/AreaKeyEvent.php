<?php

declare(strict_types=1);

namespace Libui\Draw\Params;

use Libui\Generated\Enum\ExtKey;

/** A PHP view of uiAreaKeyEvent. */
final class AreaKeyEvent
{
    public function __construct(
        public readonly int $key, // ASCII code of the key, or 0 for an extended key
        public readonly int $extKey, // see Generated\Enum\ExtKey
        public readonly int $modifier, // a modifier pressed by itself (0 otherwise)
        public readonly int $modifiers, // bitmask of modifiers held
        public readonly bool $up, // true = key released, false = key pressed
    ) {}

    public static function fromCData(\FFI\CData $e): self
    {
        return new self(
            // C `char Key` binds to a one-char PHP string in FFI; (int) cast is always 0.
            $e->Key === '' ? 0 : \ord($e->Key),
            $e->ExtKey,
            $e->Modifier,
            $e->Modifiers,
            $e->Up !== 0,
        );
    }

    /** The pressed character, or '' for an extended (non-printable) key. */
    public function char(): string
    {
        return $this->key > 0 ? \chr($this->key) : '';
    }

    /**
     * Whether this event is a key-down (press) for a specific ASCII key.
     *
     * Returns true only when the key is being pressed (not released)
     * and its ASCII code matches $asciiCode.
     */
    public function isKeyDown(int $asciiCode): bool
    {
        return !$this->up && $this->key === $asciiCode;
    }

    /**
     * Whether this event is a key-down for a specific extended key.
     *
     * Returns true only when the key is being pressed and its
     * extended key enum value matches $extKey.
     */
    public function isExtKeyDown(ExtKey $extKey): bool
    {
        return !$this->up && $this->extKey === $extKey->value;
    }

    /**
     * Whether the key is currently being pressed (vs released).
     *
     * Returns true for key-down events, false for key-up events.
     */
    public function isPressed(): bool
    {
        return !$this->up;
    }
}
