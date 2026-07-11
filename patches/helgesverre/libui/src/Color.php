<?php

declare(strict_types=1);

namespace Libui;

/**
 * An immutable RGBA colour, stored as normalized 0..1 channels (libui-native).
 *
 * The one typed way to express a colour across the binding — drawing brushes,
 * text attributes, colour buttons, table backgrounds. Construct it the way you
 * already think about colour (hex int, hex string, 0..1 floats, 8-bit ints, or a
 * named constant) and hand it to any colour-consuming API.
 *
 *   Color::rgb(0x312B90)            // hex int
 *   Color::rgba(0.19, 0.17, 0.56)   // 0..1 floats
 *   Color::rgb255(49, 43, 144)      // 8-bit ints
 *   Color::hex('#312B90')           // #RGB / #RRGGBB / #RRGGBBAA
 *   Color::black();                 // named constant
 *   Color::tomato();                // palette shortcut (see NAMED)
 *   Color::named('rebeccapurple');  // explicit palette lookup
 *   Color::hsl(210, 0.8, 0.5);      // HSL (hue°, saturation, lightness)
 *   Color::red()->lerp(Color::blue(), 0.5); // blend two colours
 *
 * Float inputs are clamped to 0..1 (forgiving); out-of-range hex/8-bit values
 * throw, since those are almost always a typo rather than rounding drift.
 *
 * @method static self red()
 * @method static self lime()
 * @method static self green()
 * @method static self blue()
 * @method static self yellow()
 * @method static self cyan()
 * @method static self magenta()
 * @method static self silver()
 * @method static self gray()
 * @method static self grey()
 * @method static self maroon()
 * @method static self olive()
 * @method static self purple()
 * @method static self teal()
 * @method static self navy()
 * @method static self orange()
 * @method static self aqua()
 * @method static self fuchsia()
 * @method static self indigo()
 * @method static self violet()
 * @method static self pink()
 * @method static self hotpink()
 * @method static self brown()
 * @method static self gold()
 * @method static self goldenrod()
 * @method static self tomato()
 * @method static self coral()
 * @method static self salmon()
 * @method static self crimson()
 * @method static self firebrick()
 * @method static self indianred()
 * @method static self lightcoral()
 * @method static self deeppink()
 * @method static self plum()
 * @method static self orchid()
 * @method static self thistle()
 * @method static self lavender()
 * @method static self mediumorchid()
 * @method static self darkorchid()
 * @method static self darkviolet()
 * @method static self darkmagenta()
 * @method static self mediumpurple()
 * @method static self slateblue()
 * @method static self mediumslateblue()
 * @method static self blueviolet()
 * @method static self rebeccapurple()
 * @method static self skyblue()
 * @method static self lightblue()
 * @method static self deepskyblue()
 * @method static self dodgerblue()
 * @method static self royalblue()
 * @method static self steelblue()
 * @method static self darkblue()
 * @method static self midnightblue()
 * @method static self powderblue()
 * @method static self cadetblue()
 * @method static self azure()
 * @method static self turquoise()
 * @method static self lightgreen()
 * @method static self seagreen()
 * @method static self forestgreen()
 * @method static self darkgreen()
 * @method static self springgreen()
 * @method static self darkcyan()
 * @method static self khaki()
 * @method static self darkkhaki()
 * @method static self lightyellow()
 * @method static self lemonchiffon()
 * @method static self lightgray()
 * @method static self lightgrey()
 * @method static self darkgray()
 * @method static self darkgrey()
 * @method static self dimgray()
 * @method static self slategray()
 * @method static self slategrey()
 * @method static self lightslategray()
 * @method static self darkslategray()
 * @method static self gainsboro()
 * @method static self whitesmoke()
 * @method static self snow()
 * @method static self slate()
 * @method static self honeydew()
 * @method static self mintcream()
 * @method static self ivory()
 * @method static self beige()
 * @method static self linen()
 * @method static self oldlace()
 * @method static self lavenderblush()
 * @method static self peachpuff()
 * @method static self seashell()
 * @method static self antiquewhite()
 * @method static self sienna()
 * @method static self saddlebrown()
 * @method static self peru()
 * @method static self tan()
 * @method static self wheat()
 * @method static self chocolate()
 * @method static self sandybrown()
 * @method static self burlywood()
 * @method static self moccasin()
 * @method static self papayawhip()
 * @method static self blanchedalmond()
 */
final class Color
{
    private function __construct(
        public readonly float $r,
        public readonly float $g,
        public readonly float $b,
        public readonly float $a,
    ) {}

    /**
     * Curated palette of named CSS colours (lowercase key → 0xRRGGBB).
     *
     * Every entry is reachable three ways:
     *   Color::tomato();               // magic __callStatic
     *   Color::named('tomato');        // explicit lookup
     *   Color::named('Light Blue');    // normalized (spaces / - / _ stripped)
     */
    public const NAMED = [
        // CSS basic
        'black' => 0x000000, 'white' => 0xFFFFFF, 'red' => 0xFF0000, 'lime' => 0x00FF00,
        'green' => 0x008000, 'blue' => 0x0000FF, 'yellow' => 0xFFFF00, 'cyan' => 0x00FFFF,
        'aqua' => 0x00FFFF, 'magenta' => 0xFF00FF, 'fuchsia' => 0xFF00FF, 'silver' => 0xC0C0C0,
        'gray' => 0x808080, 'grey' => 0x808080, 'maroon' => 0x800000, 'olive' => 0x808000,
        'purple' => 0x800080, 'teal' => 0x008080, 'navy' => 0x000080, 'orange' => 0xFFA500,
        // blues
        'skyblue' => 0x87CEEB, 'lightblue' => 0xADD8E6, 'deepskyblue' => 0x00BFFF,
        'dodgerblue' => 0x1E90FF, 'royalblue' => 0x4169E1, 'steelblue' => 0x4682B4,
        'darkblue' => 0x00008B, 'midnightblue' => 0x191970, 'powderblue' => 0xB0E0E6,
        'cadetblue' => 0x5F9EA0, 'azure' => 0xF0FFFF, 'turquoise' => 0x40E0D0,
        'slateblue' => 0x6A5ACD, 'mediumslateblue' => 0x7B68EE, 'blueviolet' => 0x8A2BE2,
        'rebeccapurple' => 0x663399, 'indigo' => 0x4B0082, 'violet' => 0xEE82EE,
        // greens
        'lightgreen' => 0x90EE90, 'seagreen' => 0x2E8B57, 'forestgreen' => 0x228B22,
        'darkgreen' => 0x006400, 'springgreen' => 0x00FF7F, 'lawngreen' => 0x7CFC00,
        'darkcyan' => 0x008B8B, 'darkseagreen' => 0x8FBC8F, 'mediumseagreen' => 0x3CB371,
        // reds / pinks
        'crimson' => 0xDC143C, 'tomato' => 0xFF6347, 'coral' => 0xFF7F50, 'salmon' => 0xFA8072,
        'firebrick' => 0xB22222, 'indianred' => 0xCD5C5C, 'lightcoral' => 0xF08080,
        'pink' => 0xFFC0CB, 'hotpink' => 0xFF69B4, 'deeppink' => 0xFF1493,
        'mistyrose' => 0xFFE4E1, 'rosybrown' => 0xBC8F8F, 'darkred' => 0x8B0000,
        // purples / magentas
        'plum' => 0xDDA0DD, 'orchid' => 0xDA70D6, 'thistle' => 0xD8BFD8,
        'lavender' => 0xE6E6FA, 'mediumorchid' => 0xBA55D3, 'darkorchid' => 0x9932CC,
        'darkviolet' => 0x9400D3, 'darkmagenta' => 0x8B008B, 'mediumpurple' => 0x9370DB,
        // browns / earth
        'brown' => 0xA52A2A, 'sienna' => 0xA0522D, 'saddlebrown' => 0x8B4513,
        'peru' => 0xCD853F, 'tan' => 0xD2B48C, 'wheat' => 0xF5DEB3, 'chocolate' => 0xD2691E,
        'sandybrown' => 0xF4A460, 'burlywood' => 0xDEB887, 'goldenrod' => 0xDAA520,
        'darkgoldenrod' => 0xB8860B, 'gold' => 0xFFD700, 'moccasin' => 0xFFE4B5,
        // yellows / khaki
        'khaki' => 0xF0E68C, 'darkkhaki' => 0xBDB76B, 'lightyellow' => 0xFFFFE0,
        'lemonchiffon' => 0xFFFACD, 'papayawhip' => 0xFFEFD5, 'blanchedalmond' => 0xFFEBCD,
        // grays / slate
        'lightgray' => 0xD3D3D3, 'lightgrey' => 0xD3D3D3, 'darkgray' => 0xA9A9A9,
        'darkgrey' => 0xA9A9A9, 'dimgray' => 0x696969, 'dimgrey' => 0x696969,
        'slategray' => 0x708090, 'slategrey' => 0x708090, 'lightslategray' => 0x778899,
        'lightslategrey' => 0x778899, 'darkslategray' => 0x2F4F4F, 'darkslategrey' => 0x2F4F4F,
        'gainsboro' => 0xDCDCDC, 'whitesmoke' => 0xF5F5F5, 'snow' => 0xFFFAFA,
        'slate' => 0x708090, 'darkslate' => 0x2F4F4F,
        // misc light tints
        'honeydew' => 0xF0FFF0, 'mintcream' => 0xF5FFFA, 'ivory' => 0xFFFFF0,
        'beige' => 0xF5F5DC, 'linen' => 0xFAF0E6, 'oldlace' => 0xFDF5E6, 'lavenderblush' => 0xFFF0F5,
        'peachpuff' => 0xFFDAB9, 'seashell' => 0xFFF5EE, 'antiquewhite' => 0xFAEBD7,
    ];

    /** Colour from 0..1 float channels. Out-of-range values are clamped. */
    public static function rgba(float $r, float $g, float $b, float $a = 1.0): self
    {
        return new self(self::clamp($r), self::clamp($g), self::clamp($b), self::clamp($a));
    }

    /** Colour from a `0xRRGGBB` integer, with optional 0..1 alpha. */
    public static function rgb(int $hex, float $a = 1.0): self
    {
        if ($hex < 0 || $hex > 0xFFFFFF) {
            throw new \InvalidArgumentException(\sprintf('Color::rgb() expects 0x000000..0xFFFFFF, got 0x%X', $hex));
        }

        return self::rgb255(($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF, $a);
    }

    /** Colour from 8-bit (0-255) channels, with optional 0..1 alpha. */
    public static function rgb255(int $r, int $g, int $b, float $a = 1.0): self
    {
        foreach (['r' => $r, 'g' => $g, 'b' => $b] as $name => $value) {
            if ($value < 0 || $value > 255) {
                throw new \InvalidArgumentException("Color::rgb255() channel {$name} out of range (0-255): {$value}");
            }
        }

        return new self($r / 255, $g / 255, $b / 255, self::clamp($a));
    }

    /** Colour from a `#RGB`, `#RRGGBB`, or `#RRGGBBAA` string (leading `#` optional). */
    public static function hex(string $hex): self
    {
        $digits = ltrim($hex, '#');

        if (preg_match('/^[0-9A-Fa-f]+$/', $digits) !== 1 || ! \in_array(\strlen($digits), [3, 6, 8], true)) {
            throw new \InvalidArgumentException("Color::hex() expects #RGB, #RRGGBB, or #RRGGBBAA, got: {$hex}");
        }

        if (\strlen($digits) === 3) {
            $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
        }

        $r = (int) hexdec(substr($digits, 0, 2));
        $g = (int) hexdec(substr($digits, 2, 2));
        $b = (int) hexdec(substr($digits, 4, 2));
        $a = \strlen($digits) === 8 ? (int) hexdec(substr($digits, 6, 2)) / 255 : 1.0;

        return new self($r / 255, $g / 255, $b / 255, $a);
    }

    /**
     * Coerce a Color or an `[r, g, b]` / `[r, g, b, a]` float array into a Color.
     *
     * Lets colour-consuming APIs accept either form; arrays default to opaque.
     *
     * @param self|array{float,float,float}|array{float,float,float,float} $color
     */
    public static function from(self|array $color): self
    {
        if ($color instanceof self) {
            return $color;
        }

        return self::rgba($color[0], $color[1], $color[2], $color[3] ?? 1.0);
    }

    public static function black(): self
    {
        return new self(0.0, 0.0, 0.0, 1.0);
    }

    public static function white(): self
    {
        return new self(1.0, 1.0, 1.0, 1.0);
    }

    public static function transparent(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0);
    }

    /**
     * Resolve a named colour from the {@see self::NAMED} palette.
     *
     * The name is normalized (lower-cased, spaces / hyphens / underscores
     * stripped) so `named('Light Blue')`, `named('light-blue')` and
     * `named('lightblue')` are equivalent. Unknown names throw.
     */
    public static function named(string $name, float $a = 1.0): self
    {
        $key = self::normalizeName($name);

        if (! isset(self::NAMED[$key])) {
            throw new \InvalidArgumentException("Unknown color name: {$name}");
        }

        return self::rgb(self::NAMED[$key], $a);
    }

    /**
     * Magic shortcut so any named palette colour is callable as a static method:
     *
     *   Color::tomato();
     *   Color::rebeccapurple(0.5);   // with alpha
     *
     * Defined methods (`black`, `white`, `transparent`, `named`, …) take
     * precedence and are never routed here. Unknown names throw.
     *
     * @param array<int, mixed> $args
     */
    public static function __callStatic(string $name, array $args): self
    {
        $key = self::normalizeName($name);

        if (! isset(self::NAMED[$key])) {
            throw new \BadMethodCallException("Call to undefined color method: Color::{$name}()");
        }

        return self::rgb(self::NAMED[$key], $args[0] ?? 1.0);
    }

    private static function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?: strtolower($name);
    }

    /** A copy with a different alpha (0..1, clamped). */
    public function withAlpha(float $a): self
    {
        return new self($this->r, $this->g, $this->b, self::clamp($a));
    }

    /**
     * The channels as a `[r, g, b, a]` float array, for the float-array APIs.
     *
     * @return array{float, float, float, float}
     */
    public function toArray(): array
    {
        return [$this->r, $this->g, $this->b, $this->a];
    }

    /** The colour as a `0xRRGGBB` integer (alpha dropped). */
    public function toHex(): int
    {
        return (self::to255($this->r) << 16) | (self::to255($this->g) << 8) | self::to255($this->b);
    }

    /**
     * Colour from HSL channels.
     *
     * @param float $h Hue in degrees (0..360); wraps automatically.
     * @param float $s Saturation (0..1).
     * @param float $l Lightness (0..1).
     * @param float $a Alpha (0..1).
     */
    public static function hsl(float $h, float $s, float $l, float $a = 1.0): self
    {
        [$r, $g, $b] = self::hslToRgb(fmod($h, 360.0) / 360.0, self::clamp($s), self::clamp($l));

        return new self($r, $g, $b, self::clamp($a));
    }

    /** A copy with a different hue (degrees, 0..360; wraps). */
    public function withHue(float $h): self
    {
        [$h0, $s, $l] = $this->toHsl();

        return self::hsl($h, $s, $l, $this->a);
    }

    /** A copy with a different saturation (0..1). */
    public function withSaturation(float $s): self
    {
        [$h, , $l] = $this->toHsl();

        return self::hsl($h, $s, $l, $this->a);
    }

    /** A copy with a different lightness (0..1). */
    public function withLightness(float $l): self
    {
        [$h, $s] = $this->toHsl();

        return self::hsl($h, $s, $l, $this->a);
    }

    /** Hue / saturation / lightness / alpha as `[h, s, l, a]` (h in degrees). */
    public function toHsl(): array
    {
        [$h, $s, $l] = self::rgbToHsl($this->r, $this->g, $this->b);

        return [$h, $s, $l, $this->a];
    }

    /**
     * Linear-interpolate toward $other by $t (0..1). Alpha is blended too.
     * Handy for gradient brushes, theme transitions, and animation tweens.
     */
    public function lerp(self $other, float $t): self
    {
        $t = self::clamp($t);

        return new self(
            $this->r + ($other->r - $this->r) * $t,
            $this->g + ($other->g - $this->g) * $t,
            $this->b + ($other->b - $this->b) * $t,
            $this->a + ($other->a - $this->a) * $t,
        );
    }

    /** Alias for {@see self::lerp()} — reads more natural when blending. */
    public function mix(self $other, float $t): self
    {
        return $this->lerp($other, $t);
    }

    /** WCAG relative luminance — 1.0 is white, 0.0 is black. */
    public function luminance(): float
    {
        $lin = static fn (float $c): float => $c <= 0.03928
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($this->r) + 0.7152 * $lin($this->g) + 0.0722 * $lin($this->b);
    }

    /** True when the colour reads as "light" (luminance ≥ 0.5). */
    public function isLight(): bool
    {
        return $this->luminance() >= 0.5;
    }

    /** Auto-contrast foreground: black on light colours, white on dark. */
    public function contrastColor(): self
    {
        return $this->isLight() ? self::black() : self::white();
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private static function to255(float $value): int
    {
        return (int) round($value * 255);
    }

    /** RGB (0..1) → HSL (h in degrees, s/l in 0..1). */
    private static function rgbToHsl(float $r, float $g, float $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2.0;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2.0 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6.0 : 0.0),
            $g => ($b - $r) / $d + 2.0,
            default => ($r - $g) / $d + 4.0,
        };

        return [$h / 6.0 * 360.0, $s, $l];
    }

    /** HSL (h in 0..1, s/l in 0..1) → RGB (0..1). */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            return [$l, $l, $l];
        }

        $q = $l < 0.5 ? $l * (1.0 + $s) : $l + $s - $l * $s;
        $p = 2.0 * $l - $q;

        $wrap = static fn (float $t): float => match (true) {
            $t < 0.0 => $t + 1.0,
            $t > 1.0 => $t - 1.0,
            default => $t,
        };

        $channel = static function (float $t) use ($p, $q, $wrap): float {
            $t = $wrap($t);
            if ($t < 1.0 / 6.0) {
                return $p + ($q - $p) * 6.0 * $t;
            }
            if ($t < 1.0 / 2.0) {
                return $q;
            }
            if ($t < 2.0 / 3.0) {
                return $p + ($q - $p) * (2.0 / 3.0 - $t) * 6.0;
            }

            return $p;
        };

        return [$channel($h + 1.0 / 3.0), $channel($h), $channel($h - 1.0 / 3.0)];
    }
}
