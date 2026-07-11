<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

use Libui\Color;

/**
 * Fluent, fully optional configuration for a {@see Chart}.
 *
 * Every setter returns $this, so charts can be configured inline:
 *
 *     (new Chart(ChartType::Bar))
 *         ->config(fn (ChartConfig $c) => $c->title('Sales')->showLegend(false));
 *
 * Sensible premium defaults (named-colour categorical palette, soft grid, 600ms
 * ease-out animation) are applied in the constructor; override only what you
 * need. The font family is resolved per-platform so CJK labels render on
 * macOS / Windows / Linux without caller intervention.
 */
final class ChartConfig
{
    /**
     * Categorical palette expressed as CSS named-colour names, resolved from
     * {@see Color::NAMED} via {@see self::palette()}. High-contrast and
     * hue-diverse, so neighbouring series stay distinguishable.
     */
    public const PALETTE_NAMES = [
        'slateblue', 'mediumseagreen', 'orange', 'crimson', 'dodgerblue',
        'blueviolet', 'hotpink', 'teal', 'tomato', 'lawngreen',
    ];

    /** Lazy-resolved palette cache (0xRRGGBB ints). */
    private static ?array $palette = null;

    /** Colour presets keyed by theme name. Each entry overrides the matching
     *  public colour fields on {@see ChartConfig}; palette/geometry untouched. */
    /**
     * Ordered list of the integer colour fields a theme overrides. Used both by
     * {@see self::applyThemeColors()} and the {@see self::interpolateTheme()}
     * colour-tween helper so the two stay in sync.
     */
    public const THEMED_FIELDS = [
        'background', 'plotBackground', 'titleColor', 'legendColor',
        'gridColor', 'axisColor', 'axisLabelColor',
        'tooltipBackground', 'tooltipText', 'tooltipBorder',
    ];

    public const THEMES = [
        'light' => [
            'background' => 0xFFFFFF,
            'plotBackground' => 0xFFFFFF,
            'titleColor' => 0x0F172A,
            'legendColor' => 0x475569,
            'gridColor' => 0xE2E8F0,
            'axisColor' => 0x94A3B8,
            'axisLabelColor' => 0x64748B,
            'tooltipBackground' => 0x1E293B,
            'tooltipText' => 0xF8FAFC,
            'tooltipBorder' => 0x334155,
        ],
        'dark' => [
            'background' => 0x0F172A,
            'plotBackground' => 0x0B1220,
            'titleColor' => 0xF8FAFC,
            'legendColor' => 0xCBD5E1,
            'gridColor' => 0x1E293B,
            'axisColor' => 0x334155,
            'axisLabelColor' => 0x94A3B8,
            'tooltipBackground' => 0xF8FAFC,
            'tooltipText' => 0x0F172A,
            'tooltipBorder' => 0xCBD5E1,
        ],
    ];

    public string $title = '';
    public bool $showTitle = true;
    public int $titleColor = 0x0F172A;
    public float $titleSize = 18.0;

    /** Active colour theme name (see {@see ChartConfig::THEMES}). */
    public string $theme = 'light';

    public bool $showLegend = true;
    public string $legendPosition = 'right'; // right | top | bottom
    public int $legendColor = 0x475569;

    public bool $showGrid = true;
    public int $gridColor = 0xE2E8F0;
    public float $gridLineWidth = 1.0;

    public bool $showAxisX = true;
    public bool $showAxisY = true;
    public int $axisColor = 0x94A3B8;
    public int $axisLabelColor = 0x64748B;
    public float $axisFontSize = 11.0;

    /** Show value labels on data points/bars when not overridden per-dataset. */
    public ?bool $showValues = false;

    public bool $animate = true;
    public float $animationDuration = 600.0;
    public bool $yZeroBased = true;

    /** Allow wheel-style (Shift-drag) and double-click zoom. */
    public bool $zoomEnabled = true;
    public bool $panEnabled = true;
    public float $maxZoom = 16.0;

    /** Per-instance palette override (0xRRGGBB ints); takes precedence over {@see self::PALETTE_NAMES}. */
    public ?array $customPalette = null;

    /**
     * Lightness delta (0..1) applied per "ring" when generating light/dark
     * variants for series beyond the base palette (see {@see self::colorAt}).
     * Higher = more contrast between successive variants.
     */
    public float $paletteVariantStep = 0.13;

    public int $background = 0xFFFFFF;
    public int $plotBackground = 0xFFFFFF;

    /** Tooltip colours (also themed by {@see ChartConfig::applyTheme}). */
    public int $tooltipBackground = 0x1E293B;
    public int $tooltipText = 0xF8FAFC;
    public int $tooltipBorder = 0x334155;

    /** [top, right, bottom, left] in px. */
    public array $padding = [16.0, 16.0, 16.0, 16.0];

    public string $fontFamily;
    public float $fontSize = 12.0;

    public function __construct()
    {
        $this->fontFamily = self::defaultFontFamily();
    }

    /** Cross-platform UI font (mirrors the game's resolution). */
    public static function defaultFontFamily(): string
    {
        $env = getenv('UI_FONT');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $os = strtoupper(substr((string) PHP_OS, 0, 3));
        if ($os === 'WIN') {
            return 'Microsoft YaHei';
        }
        if ($os === 'LIN') {
            return 'Noto Sans CJK SC';
        }

        return '.AppleSystemUIFont';
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function showLegend(bool $show = true, string $position = 'right'): self
    {
        $this->showLegend = $show;
        $this->legendPosition = $position;

        return $this;
    }

    public function showGrid(bool $show = true): self
    {
        $this->showGrid = $show;

        return $this;
    }

    public function showValues(?bool $show): self
    {
        $this->showValues = $show;

        return $this;
    }

    public function animation(float $durationMs, bool $enabled = true): self
    {
        $this->animationDuration = max(0.0, $durationMs);
        $this->animate = $enabled;

        return $this;
    }

    public function zoom(bool $enabled = true, ?float $max = null): self
    {
        $this->zoomEnabled = $enabled;
        if ($max !== null) {
            $this->maxZoom = $max;
        }

        return $this;
    }

    public function padding(float $top, float $right, float $bottom, float $left): self
    {
        $this->padding = [$top, $right, $bottom, $left];

        return $this;
    }

    /**
     * Replace the categorical palette with explicit 0xRRGGBB values.
     * Chainable; overrides the named-colour default palette for this config.
     */
    public function colors(int ...$hex): self
    {
        $this->customPalette = array_values($hex);

        return $this;
    }

    /** Tune how far apart generated light/dark palette variants sit (0..1). */
    public function paletteVariantStep(float $step): self
    {
        $this->paletteVariantStep = max(0.02, min(0.5, $step));

        return $this;
    }

    /** Resolve the categorical palette into 0xRRGGBB ints (lazy, cached). */
    public function palette(): array
    {
        if ($this->customPalette !== null) {
            return $this->customPalette;
        }
        if (self::$palette === null) {
            self::$palette = array_map(
                static fn (string $name): int => Color::named($name)->toHex(),
                self::PALETTE_NAMES,
            );
        }

        return self::$palette;
    }

    /**
     * Resolve the colour for dataset / slice index $i from the palette.
     *
     * The first {@see self::PALETTE_NAMES} series use the base named colours;
     * beyond that we derive harmonic light/dark variants by shifting the base
     * colour's HSL lightness (alternating lighter / darker, growing by
     * {@see self::$paletteVariantStep} per ring) so a chart with many series
     * never collides back onto an earlier colour.
     */
    public function colorAt(int $i): int
    {
        $base = $this->palette();
        $b = count($base);
        if ($i < $b) {
            return $base[$i];
        }

        $ring = intdiv($i, $b);            // 1, 2, 3, ...
        $baseColor = Color::rgb($base[$i % $b]);
        [$h, $s, $l] = $baseColor->toHsl();
        $dir = ($ring % 2 === 1) ? 1.0 : -1.0;
        $level = intdiv($ring + 1, 2);    // ring1->1, ring2->1, ring3->2, ...
        $newL = max(0.12, min(0.9, $l + $dir * $this->paletteVariantStep * $level));

        return Color::hsl($h, $s, $newL)->toHex();
    }

    /**
     * Expand the palette into exactly $count distinct colours for $count series,
     * using {@see self::colorAt()} (so base colours + generated variants).
     *
     * @return list<int>
     */
    public function seriesPalette(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->colorAt($i);
        }

        return $out;
    }

    /** Apply a named colour theme. Unknown names fall back to 'light'. */
    public function applyTheme(string $name): self
    {
        $known = array_key_exists($name, self::THEMES);
        $theme = $known ? self::THEMES[$name] : self::THEMES['light'];
        $this->applyThemeColors($theme);
        $this->theme = $known ? $name : 'light';

        return $this;
    }

    /**
     * Bulk-assign the themed colour fields from a name→int map (no validation).
     * Used by {@see self::applyTheme()} and by the chart's colour-tween tween.
     *
     * @param array<string,int> $colors
     */
    public function applyThemeColors(array $colors): self
    {
        foreach (self::THEMED_FIELDS as $field) {
            if (array_key_exists($field, $colors)) {
                $this->{$field} = $colors[$field];
            }
        }

        return $this;
    }

    /**
     * Interpolate every themed colour field from $a to $b by $t (0..1), blending
     * channel-by-channel with {@see Color::lerp}. Powers the chart's animated
     * theme switch; $t=0 returns $a, $t=1 returns $b.
     *
     * @param array<string,int> $a
     * @param array<string,int> $b
     * @return array<string,int>
     */
    public static function interpolateTheme(array $a, array $b, float $t): array
    {
        $out = [];
        foreach (self::THEMED_FIELDS as $field) {
            $from = Color::rgb($a[$field] ?? 0xFFFFFF);
            $to = Color::rgb($b[$field] ?? 0xFFFFFF);
            $out[$field] = $from->lerp($to, $t)->toHex();
        }

        return $out;
    }
}
