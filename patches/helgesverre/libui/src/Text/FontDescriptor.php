<?php

declare(strict_types=1);

namespace Libui\Text;

use Kingbes\Phpc\Memory;
use Kingbes\Phpc\TypeCast;
use Libui\Ffi;
use Libui\Generated\Enum\TextItalic;
use Libui\Generated\Enum\TextStretch;
use Libui\Generated\Enum\TextWeight;

/**
 * The default font for a TextLayout, wrapping the uiFontDescriptor struct
 * {Family char*, Size double, Weight, Italic, Stretch}.
 *
 * The struct holds a raw char* into a C-allocated copy of the family name; both
 * the struct and that string buffer are retained on this object so libui's
 * pointers stay valid for as long as the descriptor is in use.
 *
 * PATCHED: Use Memory::addr(), Memory::copy(), TypeCast::fromString() for phpc safety.
 */
final class FontDescriptor
{
    private \FFI\CData $descriptor;
    /** The C char[] backing the struct's Family pointer; kept alive past addr(). */
    private \FFI\CData $familyBuffer;

    public function __construct(
        string $family = 'Arial',
        float $size = 14.0,
        TextWeight $weight = TextWeight::Normal,
        TextItalic $italic = TextItalic::Normal,
        TextStretch $stretch = TextStretch::Normal,
    ) {
        $ffi = Ffi::get();
        $descriptor = $ffi->new('uiFontDescriptor');

        // Allocate a NUL-terminated C copy of the family name and point at it.
        $bytes = \strlen($family);
        $buffer = $ffi->new('char[' . ($bytes + 1) . ']');
        Memory::copy($buffer, $family, $bytes);
        $buffer[$bytes] = "\0";

        // Cast array pointer to char* for the struct field.
        $descriptor->Family = $ffi->cast('char*', Memory::addr($buffer));
        $descriptor->Size = $size;
        $descriptor->Weight = $weight->value;
        $descriptor->Italic = $italic->value;
        $descriptor->Stretch = $stretch->value;

        $this->descriptor = $descriptor;
        $this->familyBuffer = $buffer;
    }

    public function setFamily(string $family): self
    {
        $ffi = Ffi::get();
        $bytes = \strlen($family);
        $buffer = $ffi->new('char[' . ($bytes + 1) . ']');
        Memory::copy($buffer, $family, $bytes);
        $buffer[$bytes] = "\0";

        $this->descriptor->Family = $ffi->cast('char*', Memory::addr($buffer));
        $this->familyBuffer = $buffer;

        return $this;
    }

    public function setSize(float $size): self
    {
        $this->descriptor->Size = $size;
        return $this;
    }

    public function setWeight(TextWeight $weight): self
    {
        $this->descriptor->Weight = $weight->value;
        return $this;
    }

    public function setItalic(TextItalic $italic): self
    {
        $this->descriptor->Italic = $italic->value;
        return $this;
    }

    public function setStretch(TextStretch $stretch): self
    {
        $this->descriptor->Stretch = $stretch->value;
        return $this;
    }

    /**
     * Build a FontDescriptor from an existing uiFontDescriptor struct (e.g. the one
     * FontButton fills). The family name is copied into a PHP-owned buffer, so the
     * source struct may be freed afterwards (libui's uiFreeFontButtonFont).
     */
    public static function fromCData(\FFI\CData $descriptor): self
    {
        return new self(
            TypeCast::fromString($descriptor->Family),
            $descriptor->Size,
            TextWeight::from($descriptor->Weight),
            TextItalic::from($descriptor->Italic),
            TextStretch::from($descriptor->Stretch),
        );
    }

    public function family(): string
    {
        return TypeCast::fromString($this->descriptor->Family);
    }

    public function size(): float
    {
        return $this->descriptor->Size;
    }

    public function weight(): TextWeight
    {
        return TextWeight::from($this->descriptor->Weight);
    }

    public function italic(): TextItalic
    {
        return TextItalic::from($this->descriptor->Italic);
    }

    public function stretch(): TextStretch
    {
        return TextStretch::from($this->descriptor->Stretch);
    }

    public function toCData(): \FFI\CData
    {
        return $this->descriptor;
    }

    public function addr(): \FFI\CData
    {
        return Memory::addr($this->descriptor);
    }

    public function handle(): \FFI\CData
    {
        return $this->addr();
    }

    public function free(): void
    {
        // The struct is on the stack/FFI, nothing to free
        // But we keep the method for API consistency
    }
}
