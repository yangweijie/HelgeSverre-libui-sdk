<?php

declare(strict_types=1);

namespace Libui;

use Kingbes\Phpc\Memory;
use Kingbes\Phpc\Pointer;
use Libui\Text\FontDescriptor;

/**
 * FontButton widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\FontButton.
 *
 * Note: libui has no font *setter* — the font is chosen by the user through the
 * native font dialog — so this exposes only a typed getter.
 *
 * PATCHED: Use Memory::addr() and Pointer::isNull() for phpc safety.
 */
class FontButton extends Generated\FontButton
{
    /**
     * The currently selected font as a typed {@see FontDescriptor}, wrapping the
     * generated output-pointer getter and freeing libui's allocated copy.
     */
    public function getFont(): FontDescriptor
    {
        $ffi = Ffi::get();
        $desc = $ffi->new('uiFontDescriptor');

        $this->font(Memory::addr($desc)); // libui fills $desc (and allocates Family)
        $font = FontDescriptor::fromCData($desc); // copies the family into a PHP buffer
        $ffi->uiFreeFontButtonFont(Memory::addr($desc)); // free libui's Family allocation

        return $font;
    }
}
