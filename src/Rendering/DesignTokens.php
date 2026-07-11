<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;

/**
 * Immutable design-token tree.
 *
 * Holds a nested map (color / radius / typography ...) using the repo's native
 * [r, g, b, a] 0..1 colour format. Tokens may reference other tokens by dotted
 * path; resolve() dereferences them recursively. applyTheme() returns a NEW
 * instance, so existing references stay valid — this mirrors the Chart theme
 * model and keeps theming side-effect free.
 */
final class DesignTokens
{
    /**
     * Default (light) theme. Colours match the values previously hard-coded in
     * CircleProgressBar / ToggleSwitch so switching to tokens changes nothing
     * visually.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT = [
        'color' => [
            'primary'      => [0.04, 0.52, 1.0, 1.0],
            'track'        => [0.88, 0.88, 0.88, 1.0],
            'onSurface'    => [0.20, 0.20, 0.20, 1.0],
            'surface'      => [1.0, 1.0, 1.0, 1.0],
            'onPrimary'    => [1.0, 1.0, 1.0, 1.0],
            'knob'         => [1.0, 1.0, 1.0, 1.0],
            'toggleOn'     => [0.20, 0.60, 1.0, 1.0],
            'toggleOff'    => [0.50, 0.50, 0.50, 0.40],
            'toggleBorder' => [0.30, 0.30, 0.30, 0.60],
            'knobBorder'   => [0.20, 0.20, 0.20, 0.30],
            // Interaction-state washes (alpha overlays drawn on top of a widget)
            'washHover'    => [0.0, 0.0, 0.0, 0.06],   // darken-on-light hover
            'washHoverLight' => [1.0, 1.0, 1.0, 0.08], // lighten-on-dark hover
            'washDisabled' => [0.0, 0.0, 0.0, 0.04],   // mute overlay when disabled
            'focusRing'    => [0.04, 0.52, 1.0, 0.95], // keyboard focus outline
            // Structural tints used by the composite controls built in this pass.
            'selection'    => [0.04, 0.52, 1.0, 0.12], // list/table row selected fill
            'scrim'        => [0.0, 0.0, 0.0, 0.45],   // modal dialog dim layer
        ],
        'radius' => [
            'sm'   => 4.0,
            'md'   => 8.0,
            'lg'   => 12.0,
            'full' => 9999.0,
        ],
        'stroke' => [
            'hairline' => 1.0,   // Retina-crisp 1px border width
        ],
        'focus' => [
            'ringWidth' => 2.0,  // focus-ring stroke width
            'ringGap'   => 3.0,  // gap between widget edge and the ring
        ],
        'typography' => [
            'body' => ['size' => 14.0, 'weight' => 400],
        ],
    ];

    /**
     * Dark theme overrides (deep-merged on top of {@see DEFAULT}).
     * Surfaces invert; interaction washes flip to lighten rather than darken.
     *
     * @var array<string, mixed>
     */
    private const DARK = [
        'color' => [
            'primary'      => [0.10, 0.60, 1.0, 1.0],
            'track'        => [0.22, 0.22, 0.24, 1.0],
            'onSurface'    => [0.92, 0.92, 0.94, 1.0],
            'surface'      => [0.12, 0.12, 0.14, 1.0],
            'washHover'    => [1.0, 1.0, 1.0, 0.08],
            'washHoverLight' => [1.0, 1.0, 1.0, 0.08],
            'washDisabled' => [0.0, 0.0, 0.0, 0.30],
            'focusRing'    => [0.30, 0.70, 1.0, 0.95],
            'selection'    => [0.30, 0.70, 1.0, 0.20],
            'scrim'        => [0.0, 0.0, 0.0, 0.55],
        ],
        'focus' => [
            'ringWidth' => 2.0,
            'ringGap'   => 3.0,
        ],
    ];

    /**
     * @param array<string, mixed> $tokens
     */
    public function __construct(private readonly array $tokens = self::DEFAULT)
    {
    }

    /**
     * Resolve a dotted path (e.g. "color.primary") to its value.
     *
     * A string value that itself is a dotted path is treated as a reference and
     * dereferenced recursively (depth-guarded against cycles).
     *
     * @throws \OutOfBoundsException when a segment is missing.
     */
    public function resolve(string $path): mixed
    {
        return $this->resolveIn($this->tokens, $path, 0);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function resolveIn(array $node, string $path, int $depth): mixed
    {
        if ($depth > 16) {
            throw new \RuntimeException("Token reference cycle detected at: {$path}");
        }

        $value = $node;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($value) || ! array_key_exists($seg, $value)) {
                throw new \OutOfBoundsException("Token path not found: {$path}");
            }
            $value = $value[$seg];
        }

        return is_string($value)
            ? $this->resolveIn($this->tokens, $value, $depth + 1)
            : $value;
    }

    /**
     * Resolve a colour token to a Libui Color (0..1 channels).
     */
    public function color(string $path): Color
    {
        $v = $this->resolve($path);
        if (! is_array($v) || count($v) < 3) {
            throw new \UnexpectedValueException("Token '{$path}' is not a colour");
        }

        return Color::rgba(
            (float) $v[0],
            (float) $v[1],
            (float) $v[2],
            (float) ($v[3] ?? 1.0),
        );
    }

    public function number(string $path): float
    {
        $v = $this->resolve($path);
        if (! is_numeric($v)) {
            throw new \UnexpectedValueException("Token '{$path}' is not numeric");
        }

        return (float) $v;
    }

    public function has(string $path): bool
    {
        try {
            $this->resolve($path);

            return true;
        } catch (\OutOfBoundsException) {
            return false;
        }
    }

    /**
     * Convenience accessors for the interaction-state tokens added in Phase 10.
     * Each reads a single well-known path so renderers never hard-code colours.
     */

    /** Hover wash overlay (darken on light themes, lighten on dark themes). */
    public function hoverWash(): Color
    {
        return $this->color('color.washHover');
    }

    /** Disabled mute overlay. */
    public function disabledWash(): Color
    {
        return $this->color('color.washDisabled');
    }

    /** Keyboard focus-ring colour. */
    public function focusRing(): Color
    {
        return $this->color('color.focusRing');
    }

    /** Retina-crisp hairline stroke width. */
    public function hairlineWidth(): float
    {
        return $this->number('stroke.hairline');
    }

    /** Focus-ring stroke width. */
    public function focusRingWidth(): float
    {
        return $this->number('focus.ringWidth');
    }

    /** Gap between a widget's edge and its focus ring. */
    public function focusRingGap(): float
    {
        return $this->number('focus.ringGap');
    }

    /** Selected-row tint for list / table controls. */
    public function selection(): Color
    {
        return $this->color('color.selection');
    }

    /** Modal dialog dim layer drawn behind the dialog card. */
    public function scrim(): Color
    {
        return $this->color('color.scrim');
    }

    /**
     * Return a dark-theme token tree (DEFAULT deep-merged with {@see DARK}).
     */
    public static function dark(): self
    {
        return new self(self::merge(self::DEFAULT, self::DARK));
    }

    /**
     * Snap a rect's edges to device pixels so a hairline (1px logical) stroke
     * lands on a crisp pixel boundary instead of blurring across two — the
     * classic Retina sub-pixel-border problem the reference native SDK solves in
     * snapHairlineStrokeRect. $scale is the device pixel ratio (2 on Retina).
     *
     * @return array{0:float,1:float,2:float,3:float} [x, y, w, h]
     */
    public static function snapHairlineRect(
        float $x,
        float $y,
        float $w,
        float $h,
        float $scale = 2.0,
    ): array {
        $snap = static fn (float $v): float => round($v * $scale) / $scale;

        $x2 = $snap($x);
        $y2 = $snap($y);
        $w2 = $snap($x + $w) - $x2;
        $h2 = $snap($y + $h) - $y2;

        return [$x2, $y2, $w2, $h2];
    }

    /**
     * Return a NEW token tree with $overrides deep-merged on top.
     * The receiver is never mutated.
     *
     * @param array<string, mixed> $overrides
     */
    public function applyTheme(array $overrides): self
    {
        return new self(self::merge($this->tokens, $overrides));
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private static function merge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = self::merge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }

        return $a;
    }
}
