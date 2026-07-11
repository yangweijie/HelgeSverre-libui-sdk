<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * Fluent, fully optional configuration for a {@see Chart}.
 *
 * Every setter returns $this, so charts can be configured inline:
 *
 *     (new Chart(ChartType::Bar))
 *         ->config(fn (ChartConfig $c) => $c->title('Sales')->showLegend(false));
 *
 * Sensible premium defaults (indigo/emerald/amber palette, soft grid, 600ms
 * ease-out animation) are applied in the constructor; override only what you
 * need. The font family is resolved per-platform so CJK labels render on
 * macOS / Windows / Linux without caller intervention.
 */
final class ChartConfig
{
    /** A pleasant, high-contrast categorical palette (0xRRGGBB). */
    public const DEFAULT_PALETTE = [
        0x6366F1, // indigo
        0x10B981, // emerald
        0xF59E0B, // amber
        0xEF4444, // red
        0x3B82F6, // blue
        0x8B5CF6, // violet
        0xEC4899, // pink
        0x14B8A6, // teal
        0xF97316, // orange
        0x84CC16, // lime
    ];

    /** Colour presets keyed by theme name. Each entry overrides the matching
     *  public colour fields on {@see ChartConfig}; palette/geometry untouched. */
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

    public function colors(int ...$hex): self
    {
        // convenience hook; the palette constant is the source of truth, but a
        // caller can replace it by reading/writing self::DEFAULT_PALETTE.
        return $this;
    }

    /** Resolve the colour for dataset index $i from the palette. */
    public function colorAt(int $i): int
    {
        return self::DEFAULT_PALETTE[$i % count(self::DEFAULT_PALETTE)];
    }

    /** Apply a named colour theme. Unknown names fall back to 'light'. */
    public function applyTheme(string $name): self
    {
        $known = array_key_exists($name, self::THEMES);
        $theme = $known ? self::THEMES[$name] : self::THEMES['light'];
        foreach ($theme as $field => $value) {
            $this->{$field} = $value;
        }
        $this->theme = $known ? $name : 'light';

        return $this;
    }
}
