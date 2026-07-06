<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Stop;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Text\FontDescriptor;
use Yangweijie\Ui2\EmitsEvents;
use Kaareln\SVGPathData\Attributes\SVGPathData;
use Kaareln\SVGPathData\Attributes\PathData\Move;
use Kaareln\SVGPathData\Attributes\PathData\Line;
use Kaareln\SVGPathData\Attributes\PathData\RelativeMove;
use Kaareln\SVGPathData\Attributes\PathData\RelativeLine;
use Kaareln\SVGPathData\Attributes\PathData\HorizontalLine;
use Kaareln\SVGPathData\Attributes\PathData\RelativeHorizontalLine;
use Kaareln\SVGPathData\Attributes\PathData\VerticalLine;
use Kaareln\SVGPathData\Attributes\PathData\RelativeVerticalLine;
use Kaareln\SVGPathData\Attributes\PathData\BezierCurve;
use Kaareln\SVGPathData\Attributes\PathData\RelativeBezierCurve;
use Kaareln\SVGPathData\Attributes\PathData\QuadraticCurve;
use Kaareln\SVGPathData\Attributes\PathData\RelativeQuadraticCurve;
use Kaareln\SVGPathData\Attributes\PathData\ArcCurve;
use Kaareln\SVGPathData\Attributes\PathData\RelativeArcCurve;
use Kaareln\SVGPathData\Attributes\PathData\ClosePath;

/**
 * @internal Area delegate for SVG rendering with mouse interaction support.
 *
 * Supported features:
 *  - Solid colors (hex / rgb / rgba / named), opacity
 *  - Gradient paints via `url(#gradientId)` (linear & radial, both
 *    `userSpaceOnUse` and `objectBoundingBox` units)
 *  - `<style>` CSS inheritance (type / `.class` / `#id` selectors, descendant
 *    combinator, source-order + specificity cascade)
 *  - Dashed strokes via `stroke-dasharray` / `stroke-dashoffset`
 */
final class SvgDelegate extends AreaDelegate
{
    use EmitsEvents;

    /**
     * @var list<array{
     *      d?: string,
     *      type?: string,
     *      fill: ?string, stroke: ?string, strokeWidth: float, opacity: float,
     *      dash: list<float>, dashPhase: float,
     *      bounds?: array,
     *      cx?: float, cy?: float, r?: float, rx?: float, ry?: float,
     *      x?: float, y?: float, w?: float, h?: float,
     *      text?: string, fontSize?: float
     * }>
     */
    private array $elements = [];

    /** Gradient registry: id => gradient definition. */
    private array $gradients = [];

    /** Parsed CSS rules, ordered by specificity (descending, stable). */
    private array $cssRules = [];

    /** Index of the element currently under the mouse cursor, or -1. */
    private ?int $hoveredIndex = null;

    /** Whether the mouse is currently inside the SVG area. */
    private bool $entered = false;

    public int $width = 200;
    public int $height = 200;

    public function parse(string $svgContent): void
    {
        $this->elements = [];
        $this->gradients = [];
        $this->cssRules = [];

        $xml = @\simplexml_load_string($svgContent);
        if ($xml === false) {
            return;
        }

        // Get SVG dimensions
        $this->width = (int) ($xml['width'] ?? 200);
        $this->height = (int) ($xml['height'] ?? 200);

        // Register namespace for XPath
        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');

        // Collect <defs> gradients and <style> rules up front
        $this->collectGradients($xml);
        $this->collectStyles($xml);

        // Seed inherited style from the root <svg> element (fill / stroke / ...)
        $root = $xml->attributes();
        $inherited = [
            'fill' => isset($root['fill']) ? (string) $root['fill'] : null,
            'stroke' => isset($root['stroke']) ? (string) $root['stroke'] : null,
            'strokeWidth' => isset($root['stroke-width']) ? (float) $root['stroke-width'] : 1.0,
            'opacity' => 1.0,
            'dash' => [],
            'dashPhase' => 0.0,
        ];

        $this->parseElements($xml, $inherited, [['tag' => 'svg', 'classes' => [], 'id' => null]]);
    }

    // ── Defs / gradients ──────────────────────────────────────────────────

    private function collectGradients(\SimpleXMLElement $xml): void
    {
        $nodes = $xml->xpath('//s:linearGradient | //s:radialGradient');
        if ($nodes === false) {
            return;
        }
        foreach ($nodes as $g) {
            $attrs = $g->attributes();
            $id = isset($attrs['id']) ? (string) $attrs['id'] : null;
            if ($id === null) {
                continue;
            }
            $type = $g->getName() === 'radialGradient' ? 'radial' : 'linear';
            $this->gradients[$id] = $this->parseGradient($g, $type);
        }
    }

    /**
     * @return array{type: string, units: string, coords: array, stops: list<array{offset: float, color: string, opacity: float}>}
     */
    private function parseGradient(\SimpleXMLElement $g, string $type): array
    {
        $attrs = $g->attributes();
        $units = isset($attrs['gradientUnits']) && (string) $attrs['gradientUnits'] === 'userSpaceOnUse'
            ? 'userSpaceOnUse'
            : 'objectBoundingBox';

        $coords = [];
        if ($type === 'radial') {
            $coords = [
                'cx' => $this->frac($attrs['cx'] ?? '50%'),
                'cy' => $this->frac($attrs['cy'] ?? '50%'),
                'r' => $this->frac($attrs['r'] ?? '50%'),
            ];
        } else {
            $coords = [
                'x1' => $this->frac($attrs['x1'] ?? '0%'),
                'y1' => $this->frac($attrs['y1'] ?? '0%'),
                'x2' => $this->frac($attrs['x2'] ?? '100%'),
                'y2' => $this->frac($attrs['y2'] ?? '0%'),
            ];
        }

        $stops = [];
        foreach ($g->children() as $stop) {
            if ($stop->getName() !== 'stop') {
                continue;
            }
            $sa = $stop->attributes();
            $offset = $this->frac($sa['offset'] ?? '0%');
            $color = isset($sa['stop-color']) ? (string) $sa['stop-color'] : null;
            $opacity = isset($sa['stop-opacity']) ? (float) $sa['stop-opacity'] : 1.0;
            // stop-color may also be declared via a style attribute
            if ($color === null && isset($sa['style'])) {
                $color = $this->inlineProp((string) $sa['style'], 'stop-color');
                $op = $this->inlineProp((string) $sa['style'], 'stop-opacity');
                if ($op !== null) {
                    $opacity = (float) $op;
                }
            }
            $stops[] = [
                'offset' => $offset,
                'color' => $color ?? '#000000',
                'opacity' => $opacity,
            ];
        }

        return ['type' => $type, 'units' => $units, 'coords' => $coords, 'stops' => $stops];
    }

    // ── <style> CSS ───────────────────────────────────────────────────────

    private function collectStyles(\SimpleXMLElement $xml): void
    {
        $nodes = $xml->xpath('//s:style');
        if ($nodes === false) {
            return;
        }
        $rules = [];
        foreach ($nodes as $style) {
            $text = (string) $style;
            if ($text === '') {
                continue;
            }
            $rules = [...$rules, ...$this->parseCss($text)];
        }
        // Stable sort by specificity (ascending) so higher-specificity rules are
        // applied last and win the cascade. PHP 8+ usort is stable, so
        // equal-specificity rules keep their source order.
        \usort($rules, static fn (array $a, array $b): int => $a['specificity'] <=> $b['specificity']);
        $this->cssRules = $rules;
    }

    /**
     * Parse a CSS block into ordered rules.
     *
     * @return list<array{parts: list<array{tag: ?string, classes: list<string>, id: ?string}>, props: array<string,string>, specificity: int}>
     */
    private function parseCss(string $css): array
    {
        $css = (string) \preg_replace('/\/\*.*?\*\//s', '', $css); // strip comments
        $rules = [];
        if (!\preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $blocks, \PREG_SET_ORDER)) {
            return $rules;
        }
        foreach ($blocks as $b) {
            $selectorStr = \trim($b[1]);
            $declStr = \trim($b[2]);
            $selectors = \array_filter(\array_map('trim', \explode(',', $selectorStr)));
            $props = [];
            foreach (\explode(';', $declStr) as $decl) {
                if (!\str_contains($decl, ':')) {
                    continue;
                }
                [$prop, $val] = \explode(':', $decl, 2);
                $prop = \strtolower(\trim($prop));
                $val = \trim($val);
                if ($val === '') {
                    continue;
                }
                $props[$prop] = $val;
            }
            if ($selectors === [] || $props === []) {
                continue;
            }
            // Each comma-separated selector is an independent rule with its own
            // descendant chain (split on whitespace into compound selectors).
            foreach ($selectors as $selStr) {
                $chain = \array_values(\array_filter(\explode(' ', $selStr)));
                if ($chain === []) {
                    continue;
                }
                $compounds = \array_map($this->parseCompoundSelector(...), $chain);
                $specificity = 0;
                foreach ($compounds as $p) {
                    $specificity = \max($specificity, $this->compoundSpecificity($p));
                }
                $rules[] = ['parts' => $compounds, 'props' => $props, 'specificity' => $specificity];
            }
        }
        return $rules;
    }

    /**
     * @return array{tag: ?string, classes: list<string>, id: ?string}
     */
    private function parseCompoundSelector(string $sel): array
    {
        $sel = \trim($sel);
        $id = null;
        $classes = [];
        if (\preg_match('/#([\w-]+)/', $sel, $m)) {
            $id = $m[1];
        }
        if (\preg_match_all('/\.([\w-]+)/', $sel, $m)) {
            $classes = $m[1];
        }
        $tag = (string) \preg_replace('/[.#][\w-]+/', '', $sel);
        $tag = \trim($tag) !== '' ? $tag : null;
        return ['tag' => $tag, 'classes' => $classes, 'id' => $id];
    }

    /**
     * @param array{tag: ?string, classes: list<string>, id: ?string} $c
     */
    private function compoundSpecificity(array $c): int
    {
        return ($c['id'] !== null ? 100 : 0)
            + \count($c['classes']) * 10
            + ($c['tag'] !== null ? 1 : 0);
    }

    // ── Element parsing ───────────────────────────────────────────────────

    /**
     * @param \SimpleXMLElement $xml
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $inherited
     * @param list<array{tag: string, classes: list<string>, id: ?string}> $ancestors
     */
    private function parseElements(\SimpleXMLElement $xml, array $inherited = [], array $ancestors = []): void
    {
        foreach ($xml->children() as $child) {
            $tag = $child->getName();
            // Structural nodes handled elsewhere — never drawn.
            if ($tag === 'defs' || $tag === 'style' || $tag === 'title' || $tag === 'desc') {
                continue;
            }

            $attrs = $child->attributes();
            $classes = isset($attrs['class']) ? \array_values(\array_filter(\preg_split('/\s+/', \trim((string) $attrs['class'])))) : [];
            $id = isset($attrs['id']) ? (string) $attrs['id'] : null;
            $entry = ['tag' => $tag, 'classes' => $classes, 'id' => $id];

            $s = $this->resolveStyle($child, $inherited, $ancestors);

            match ($tag) {
                'path' => $this->addElement($child, $s),
                'rect' => $this->addRect($child, $s),
                'circle' => $this->addCircle($child, $s),
                'ellipse' => $this->addEllipse($child, $s),
                'line' => $this->addLine($child, $s),
                'polygon' => $this->addPolygon($child, $s),
                'polyline' => $this->addPolyline($child, $s),
                'text' => $this->addText($child, $s),
                'g' => $this->parseGroup($child, $s, [...$ancestors, $entry]),
                'use' => null, // not supported
                default => null,
            };
        }
    }

    /**
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     * @param list<array{tag: string, classes: list<string>, id: ?string}> $ancestors
     * @return array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float}
     */
    private function resolveStyle(\SimpleXMLElement $child, array $inherited, array $ancestors): array
    {
        $attrs = $child->attributes();
        $tag = $child->getName();
        $classes = isset($attrs['class']) ? \array_values(\array_filter(\preg_split('/\s+/', \trim((string) $attrs['class'])))) : [];
        $id = isset($attrs['id']) ? (string) $attrs['id'] : null;

        $s = [
            'fill' => $inherited['fill'] ?? null,
            'stroke' => $inherited['stroke'] ?? null,
            'strokeWidth' => $inherited['strokeWidth'] ?? 1.0,
            'opacity' => $inherited['opacity'] ?? 1.0,
            'dash' => $inherited['dash'] ?? [],
            'dashPhase' => $inherited['dashPhase'] ?? 0.0,
        ];

        // Presentation attributes override inherited values (CSS beats these).
        if (isset($attrs['fill'])) $s['fill'] = (string) $attrs['fill'];
        if (isset($attrs['stroke'])) $s['stroke'] = (string) $attrs['stroke'];
        if (isset($attrs['stroke-width'])) $s['strokeWidth'] = (float) $attrs['stroke-width'];
        if (isset($attrs['opacity'])) $s['opacity'] = (float) $attrs['opacity'];
        if (isset($attrs['stroke-dasharray'])) $s['dash'] = $this->parseDashArray((string) $attrs['stroke-dasharray']);
        if (isset($attrs['stroke-dashoffset'])) $s['dashPhase'] = (float) $attrs['stroke-dashoffset'];

        // CSS stylesheet rules (beat presentation attributes).
        foreach ($this->cssRules as $rule) {
            if ($this->matchesRule($rule, $tag, $classes, $id, $ancestors)) {
                $this->applyProps($s, $rule['props']);
            }
        }

        // Inline style attribute (highest priority).
        if (isset($attrs['style'])) {
            $this->applyProps($s, $this->parseInlineStyle((string) $attrs['style']));
        }

        return $s;
    }

    /**
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     * @param array<string,string> $props
     */
    private function applyProps(array &$s, array $props): void
    {
        if (isset($props['fill'])) $s['fill'] = $props['fill'];
        if (isset($props['stroke'])) $s['stroke'] = $props['stroke'];
        if (isset($props['stroke-width'])) $s['strokeWidth'] = (float) $props['stroke-width'];
        if (isset($props['opacity'])) $s['opacity'] = (float) $props['opacity'];
        if (isset($props['stroke-dasharray'])) $s['dash'] = $this->parseDashArray($props['stroke-dasharray']);
        if (isset($props['stroke-dashoffset'])) $s['dashPhase'] = (float) $props['stroke-dashoffset'];
    }

    /**
     * @param list<array{tag: string, classes: list<string>, id: ?string}> $ancestors
     */
    private function matchesRule(array $rule, string $tag, array $classes, ?string $id, array $ancestors): bool
    {
        $parts = $rule['parts'];
        if ($parts === []) {
            return false;
        }
        $last = \end($parts);
        if (!$this->matchCompound($last, $tag, $classes, $id)) {
            return false;
        }

        $prev = \array_slice($parts, 0, -1);
        if ($prev === []) {
            return true;
        }

        // Descendant combinator: walk ancestors outward looking for a match
        // for each preceding compound, right-to-left.
        $p = \count($prev) - 1;
        $ai = \count($ancestors) - 1;
        while ($p >= 0 && $ai >= 0) {
            $anc = $ancestors[$ai];
            if ($this->matchCompound($prev[$p], $anc['tag'], $anc['classes'], $anc['id'])) {
                $p--;
            }
            $ai--;
        }
        return $p < 0;
    }

    /**
     * @param array{tag: ?string, classes: list<string>, id: ?string} $c
     */
    private function matchCompound(array $c, string $tag, array $classes, ?string $id): bool
    {
        if ($c['id'] !== null && $c['id'] !== $id) {
            return false;
        }
        if ($c['tag'] !== null && $c['tag'] !== $tag) {
            return false;
        }
        foreach ($c['classes'] as $cls) {
            if (!\in_array($cls, $classes, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function parseGroup(\SimpleXMLElement $xml, array $s, array $ancestors): void
    {
        $this->parseElements($xml, $s, $ancestors);
    }

    /**
     * Parse a dash pattern. "none" -> solid. An odd number of values is
     * duplicated (SVG spec) so the on/off pattern repeats evenly.
     *
     * @return list<float>
     */
    private function parseDashArray(string $value): array
    {
        $value = \strtolower(\trim($value));
        if ($value === '' || $value === 'none') {
            return [];
        }
        $parts = \preg_split('/[\s,]+/', $value);
        if ($parts === false) {
            return [];
        }
        $dashes = \array_values(\array_filter(\array_map(
            static fn (string $v): ?float => \is_numeric($v) ? (float) $v : null,
            $parts,
        ), static fn (?float $v): bool => $v !== null));
        if ($dashes === []) {
            return [];
        }
        if (\count($dashes) % 2 === 1) {
            $dashes = [...$dashes, ...$dashes];
        }
        return $dashes;
    }

    private function parseInlineStyle(string $style): array
    {
        $props = [];
        foreach (\explode(';', $style) as $decl) {
            if (!\str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = \explode(':', $decl, 2);
            $prop = \strtolower(\trim($prop));
            $val = \trim($val);
            if ($val === '') {
                continue;
            }
            $props[$prop] = $val;
        }
        return $props;
    }

    private function inlineProp(string $style, string $prop): ?string
    {
        $props = $this->parseInlineStyle($style);
        return $props[$prop] ?? null;
    }

    /**
     * Parse a coordinate / offset. Percentages are converted to fractions
     * (0..1). Plain numbers are returned as-is.
     */
    private function frac(mixed $value): float
    {
        $value = \trim((string) $value);
        if (\str_ends_with($value, '%')) {
            return (float) \substr($value, 0, -1) / 100.0;
        }
        return (float) $value;
    }

    /**
     * Compute a bounding box from an SVG path `d` string by extracting all
     * coordinate pairs.  Approximation — does not account for curve control
     * points that extend beyond the path geometry, but good enough for hit-
     * testing in typical icons and illustrations.
     *
     * @return array{minX: float, minY: float, maxX: float, maxY: float}
     */
    private function pathBounds(string $d): array
    {
        $minX = $maxX = $minY = $maxY = null;

        if (\preg_match_all('/[MmLlCcQqAaSsTtVvHh]\s*([-\d.e]+)\s*[, ]\s*([-\d.e]+)/', $d, $pairs, \PREG_SET_ORDER)) {
            foreach ($pairs as $m) {
                $x = (float) $m[1];
                $y = (float) $m[2];
                if ($minX === null || $x < $minX) $minX = $x;
                if ($maxX === null || $x > $maxX) $maxX = $x;
                if ($minY === null || $y < $minY) $minY = $y;
                if ($maxY === null || $y > $maxY) $maxY = $y;
            }
        }

        // Also extract single-coordinate commands (H, V, h, v)
        if (\preg_match_all('/[Hh]\s*([-\d.e]+)/', $d, $hits)) {
            foreach ($hits[1] as $x) {
                $x = (float) $x;
                if ($minX === null || $x < $minX) $minX = $x;
                if ($maxX === null || $x > $maxX) $maxX = $x;
            }
        }
        if (\preg_match_all('/[Vv]\s*([-\d.e]+)/', $d, $hits)) {
            foreach ($hits[1] as $y) {
                $y = (float) $y;
                if ($minY === null || $y < $minY) $minY = $y;
                if ($maxY === null || $y > $maxY) $maxY = $y;
            }
        }

        $minX ??= 0.0; $maxX ??= 0.0;
        $minY ??= 0.0; $maxY ??= 0.0;

        // Pad slightly so points exactly on the edge still register
        $pad = 2.0;
        return [
            'minX' => $minX - $pad,
            'minY' => $minY - $pad,
            'maxX' => $maxX + $pad,
            'maxY' => $maxY + $pad,
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addElement(\SimpleXMLElement $el, array $s): void
    {
        $d = (string) ($el['d'] ?? '');
        if ($d === '') {
            return;
        }
        $this->elements[] = [
            'd' => $d,
            ...$this->styleSlice($s),
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addRect(\SimpleXMLElement $el, array $s): void
    {
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $w = (float) ($el['width'] ?? 0);
        $h = (float) ($el['height'] ?? 0);
        $d = "M {$x} {$y} L " . ($x + $w) . " {$y} L " . ($x + $w) . " " . ($y + $h) . " L {$x} " . ($y + $h) . " Z";

        $this->elements[] = [
            'd' => $d, ...$this->styleSlice($s),
            'bounds' => ['minX' => $x - 1, 'minY' => $y - 1, 'maxX' => $x + $w + 1, 'maxY' => $y + $h + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addCircle(\SimpleXMLElement $el, array $s): void
    {
        $cx = (float) ($el['cx'] ?? 0);
        $cy = (float) ($el['cy'] ?? 0);
        $r = (float) ($el['r'] ?? 0);
        $this->elements[] = [
            'type' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $r,
            ...$this->styleSlice($s),
            'bounds' => ['minX' => $cx - $r - 1, 'minY' => $cy - $r - 1, 'maxX' => $cx + $r + 1, 'maxY' => $cy + $r + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addEllipse(\SimpleXMLElement $el, array $s): void
    {
        $cx = (float) ($el['cx'] ?? 0);
        $cy = (float) ($el['cy'] ?? 0);
        $rx = (float) ($el['rx'] ?? 0);
        $ry = (float) ($el['ry'] ?? 0);
        $this->elements[] = [
            'type' => 'ellipse', 'cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry,
            ...$this->styleSlice($s),
            'bounds' => ['minX' => $cx - $rx - 1, 'minY' => $cy - $ry - 1, 'maxX' => $cx + $rx + 1, 'maxY' => $cy + $ry + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addLine(\SimpleXMLElement $el, array $s): void
    {
        $x1 = (float) ($el['x1'] ?? 0);
        $y1 = (float) ($el['y1'] ?? 0);
        $x2 = (float) ($el['x2'] ?? 0);
        $y2 = (float) ($el['y2'] ?? 0);
        $d = "M {$x1} {$y1} L {$x2} {$y2}";
        $minX = \min($x1, $x2) - 1; $maxX = \max($x1, $x2) + 1;
        $minY = \min($y1, $y2) - 1; $maxY = \max($y1, $y2) + 1;
        // Lines without an explicit stroke are invisible in SVG; keep the
        // previous black-stroke default for safety.
        $s['stroke'] ??= '#000000';
        $this->elements[] = [
            'd' => $d, ...$this->styleSlice($s),
            'bounds' => ['minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addPolygon(\SimpleXMLElement $el, array $s): void
    {
        $points = (string) ($el['points'] ?? '');
        $coords = \preg_split('/[\s,]+/', \trim($points));
        if ($coords === false || \count($coords) < 4) {
            return;
        }
        $d = "M {$coords[0]} {$coords[1]}";
        for ($i = 2; $i < \count($coords); $i += 2) {
            $d .= " L {$coords[$i]} " . ($coords[$i + 1] ?? 0);
        }
        $d .= ' Z';
        $this->elements[] = [
            'd' => $d, ...$this->styleSlice($s),
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addPolyline(\SimpleXMLElement $el, array $s): void
    {
        $points = (string) ($el['points'] ?? '');
        $coords = \preg_split('/[\s,]+/', \trim($points));
        if ($coords === false || \count($coords) < 4) {
            return;
        }
        $d = "M {$coords[0]} {$coords[1]}";
        for ($i = 2; $i < \count($coords); $i += 2) {
            $d .= " L {$coords[$i]} " . ($coords[$i + 1] ?? 0);
        }
        $d .= ' Z';
        $this->elements[] = [
            'd' => $d, ...$this->styleSlice($s),
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     */
    private function addText(\SimpleXMLElement $el, array $s): void
    {
        $text = (string) $el;
        if ($text === '') {
            return;
        }
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $dx = (float) ($el['dx'] ?? 0);
        $dy = (float) ($el['dy'] ?? 0);
        $fontSize = 14.0;
        if (isset($el['font-size'])) {
            $fontSize = (float) $el['font-size'];
        }
        $tx = $x + $dx;
        $ty = $y + $dy;
        $approxWidth = \strlen($text) * $fontSize * 0.6; // rough monospace approximation
        $this->elements[] = [
            'type' => 'text', 'text' => $text, 'x' => $tx, 'y' => $ty,
            'fontSize' => $fontSize, 'fill' => $s['fill'], 'opacity' => $s['opacity'],
            'bounds' => ['minX' => $tx - 1, 'minY' => $ty - $fontSize - 1, 'maxX' => $tx + $approxWidth + 1, 'maxY' => $ty + 4],
        ];
    }

    /**
     * @param array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float} $s
     * @return array{fill:?string,stroke:?string,strokeWidth:float,opacity:float,dash:list<float>,dashPhase:float}
     */
    private function styleSlice(array $s): array
    {
        return [
            'fill' => $s['fill'] ?? null,
            'stroke' => $s['stroke'] ?? null,
            'strokeWidth' => $s['strokeWidth'] ?? 1.0,
            'opacity' => $s['opacity'] ?? 1.0,
            'dash' => $s['dash'] ?? [],
            'dashPhase' => $s['dashPhase'] ?? 0.0,
        ];
    }

    public function setPaths(array $paths): void
    {
        $this->elements = [];
        $this->hoveredIndex = null;
        foreach ($paths as $d) {
            $this->elements[] = [
                'd' => $d, 'fill' => '#000', 'stroke' => null,
                'strokeWidth' => 1.0, 'opacity' => 1.0, 'dash' => [], 'dashPhase' => 0.0,
                'bounds' => $this->pathBounds($d),
            ];
        }
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        foreach ($this->elements as $i => $el) {
            $type = $el['type'] ?? 'path';
            $fill = $this->resolveBrush($el['fill'] ?? 'none', $el);
            $stroke = $this->resolveBrush($el['stroke'] ?? 'none', $el);
            $sw = $el['strokeWidth'] ?? 1.0;
            $dash = $el['dash'] ?? [];
            $dashPhase = $el['dashPhase'] ?? 0.0;
            $strokeParams = new StrokeParams(thickness: $sw, dashes: $dash, dashPhase: $dashPhase);

            if ($type === 'rect') {
                $p = new \Libui\Draw\Path();
                $p->addRectangle($el['x'], $el['y'], $el['w'], $el['h']);
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, $strokeParams);
                $p->free();
            } elseif ($type === 'circle') {
                $p = new \Libui\Draw\Path();
                $p->newFigureWithArc($el['cx'], $el['cy'], $el['r'], 0, 2 * M_PI);
                $p->closeFigure();
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, $strokeParams);
                $p->free();
            } elseif ($type === 'ellipse') {
                $p = new \Libui\Draw\Path();
                $p->ellipse($el['cx'], $el['cy'], $el['rx'], $el['ry']);
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, $strokeParams);
                $p->free();
            } elseif ($type === 'line') {
                $p = new \Libui\Draw\Path();
                $p->newFigure($el['x1'], $el['y1']);
                $p->lineTo($el['x2'], $el['y2']);
                $p->end();
                if ($stroke) $ctx->stroke($p, $stroke, $strokeParams);
                $p->free();
            } elseif ($type === 'path' && isset($el['d'])) {
                $path = $this->svgPathToLibui($el['d']);
                if ($path === null) continue;
                if ($fill) $ctx->fill($path, $fill);
                if ($stroke) $ctx->stroke($path, $stroke, $strokeParams);
                $path->free();
            } elseif ($type === 'text') {
                $font = new FontDescriptor('sans-serif', $el['fontSize']);
                $color = $this->resolveTextColor($el['fill'] ?? '#000000', $el['opacity'] ?? 1.0);
                $ctx->drawString($el['text'], $font, $color, $el['x'], $el['y']);
            }

            // Hover highlight: draw a semi-transparent bounding box overlay
            if ($i === $this->hoveredIndex && isset($el['bounds'])) {
                $b = $el['bounds'];
                $hl = new \Libui\Draw\Path();
                $hl->addRectangle($b['minX'], $b['minY'], $b['maxX'] - $b['minX'], $b['maxY'] - $b['minY']);
                $hl->end();
                $ctx->fill($hl, Brush::color(Color::rgba(0.3, 0.5, 1.0, 0.15)));
                $ctx->stroke($hl, Brush::color(Color::rgba(0.3, 0.5, 1.0, 0.4)), new StrokeParams(thickness: 1.0));
                $hl->free();
            }
        }
    }

    /**
     * Resolve a paint spec ('none', solid color, or url(#id)) to a Brush.
     * Gradient references are built against the element's bounding box.
     */
    private function resolveBrush(mixed $spec, array $el): ?Brush
    {
        if ($spec === null) {
            return null;
        }
        $spec = (string) $spec;
        if (\strtolower(\trim($spec)) === 'none') {
            return null;
        }
        $s = \trim($spec);
        if (\preg_match('/^url\(\s*#([\w.-]+)\s*\)$/i', $s, $m)) {
            return $this->gradientBrush($m[1], $el);
        }
        $opacity = $el['opacity'] ?? 1.0;
        $c = $this->parseCssColor($spec);
        return Brush::color(Color::rgba($c['r'], $c['g'], $c['b'], $c['a'] * $opacity));
    }

    /**
     * Solid color only (used for text, where gradients are not supported).
     */
    private function resolveTextColor(mixed $spec, float $opacity): Color
    {
        if ($spec === null || \strtolower(\trim((string) $spec)) === 'none') {
            return Color::rgba(0, 0, 0, $opacity);
        }
        $c = $this->parseCssColor((string) $spec);
        return Color::rgba($c['r'], $c['g'], $c['b'], $c['a'] * $opacity);
    }

    /**
     * Build a linear/radial gradient Brush from the registry, mapped to the
     * element's bounding box for objectBoundingBox gradients.
     */
    private function gradientBrush(string $id, array $el): ?Brush
    {
        $g = $this->gradients[$id] ?? null;
        if ($g === null) {
            return null;
        }
        $opacity = $el['opacity'] ?? 1.0;

        $stops = [];
        foreach ($g['stops'] as $stop) {
            $c = $this->parseCssColor($stop['color']);
            $a = $c['a'] * ($stop['opacity'] ?? 1.0) * $opacity;
            $stops[] = Stop::at($stop['offset'], Color::rgba($c['r'], $c['g'], $c['b'], $a));
        }

        if ($g['type'] === 'radial') {
            [$cx, $cy, $r] = $this->radialCoords($g['coords'], $g['units'], $el);
            return Brush::radialGradient($cx, $cy, $r, $stops);
        }

        [$x0, $y0, $x1, $y1] = $this->linearCoords($g['coords'], $g['units'], $el);
        return Brush::linearGradient($x0, $y0, $x1, $y1, $stops);
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $c
     * @return array{float,float,float,float}
     */
    private function linearCoords(array $c, string $units, array $el): array
    {
        if ($units === 'userSpaceOnUse') {
            return [$c['x1'], $c['y1'], $c['x2'], $c['y2']];
        }
        $b = $this->elementBounds($el);
        $bw = $b['maxX'] - $b['minX'];
        $bh = $b['maxY'] - $b['minY'];
        return [
            $b['minX'] + $c['x1'] * $bw,
            $b['minY'] + $c['y1'] * $bh,
            $b['minX'] + $c['x2'] * $bw,
            $b['minY'] + $c['y2'] * $bh,
        ];
    }

    /**
     * @param array{cx:float,cy:float,r:float} $c
     * @return array{float,float,float}
     */
    private function radialCoords(array $c, string $units, array $el): array
    {
        if ($units === 'userSpaceOnUse') {
            return [$c['cx'], $c['cy'], $c['r']];
        }
        $b = $this->elementBounds($el);
        $bw = $b['maxX'] - $b['minX'];
        $bh = $b['maxY'] - $b['minY'];
        // Approximate the bounding-box-scaled circle radius.
        $r = $c['r'] * 0.5 * \sqrt($bw * $bw + $bh * $bh);
        return [
            $b['minX'] + $c['cx'] * $bw,
            $b['minY'] + $c['cy'] * $bh,
            $r,
        ];
    }

    /**
     * @return array{minX: float, minY: float, maxX: float, maxY: float}
     */
    private function elementBounds(array $el): array
    {
        if (isset($el['bounds'])) {
            return $el['bounds'];
        }
        $type = $el['type'] ?? null;
        if ($type === 'circle') {
            return ['minX' => $el['cx'] - $el['r'], 'minY' => $el['cy'] - $el['r'],
                    'maxX' => $el['cx'] + $el['r'], 'maxY' => $el['cy'] + $el['r']];
        }
        if ($type === 'ellipse') {
            return ['minX' => $el['cx'] - $el['rx'], 'minY' => $el['cy'] - $el['ry'],
                    'maxX' => $el['cx'] + $el['rx'], 'maxY' => $el['cy'] + $el['ry']];
        }
        return ['minX' => 0.0, 'minY' => 0.0, 'maxX' => (float) $this->width, 'maxY' => (float) $this->height];
    }

    /**
     * Parse a CSS color into normalized [r,g,b,a] floats (0..1).
     *
     * @return array{r: float, g: float, b: float, a: float}
     */
    private function parseCssColor(string $color): array
    {
        $color = \strtolower(\trim($color));

        $named = match ($color) {
            'black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000',
            'green' => '#008000', 'blue' => '#0000ff', 'yellow' => '#ffff00',
            'cyan' => '#00ffff', 'magenta' => '#ff00ff', 'gray', 'grey' => '#808080',
            'orange' => '#ffa500', 'purple' => '#800080', 'pink' => '#ffc0cb',
            'brown' => '#a52a2a', default => $color,
        };

        if (\preg_match('/^#([0-9a-f]{3,8})$/', $named, $m)) {
            $hex = $m[1];
            if (\strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            $r = \hexdec(\substr($hex, 0, 2)) / 255.0;
            $g = \hexdec(\substr($hex, 2, 2)) / 255.0;
            $b = \hexdec(\substr($hex, 4, 2)) / 255.0;
            $a = isset($hex[6]) ? \hexdec(\substr($hex, 6, 2)) / 255.0 : 1.0;
            return ['r' => $r, 'g' => $g, 'b' => $b, 'a' => $a];
        }

        if (\preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $named, $m)) {
            return ['r' => (int) $m[1] / 255.0, 'g' => (int) $m[2] / 255.0, 'b' => (int) $m[3] / 255.0, 'a' => 1.0];
        }

        if (\preg_match('/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/', $named, $m)) {
            return ['r' => (int) $m[1] / 255.0, 'g' => (int) $m[2] / 255.0, 'b' => (int) $m[3] / 255.0, 'a' => (float) $m[4]];
        }

        return ['r' => 0.0, 'g' => 0.0, 'b' => 0.0, 'a' => 1.0];
    }

    // ── Mouse interaction ──

    public function mouse(\Libui\Draw\Params\AreaMouseEvent $event): void
    {
        $x = $event->x;
        $y = $event->y;

        // Hit-test: find the topmost element under the cursor
        $hitIndex = $this->hitTest($x, $y);

        // Hover-change detection
        if ($hitIndex !== $this->hoveredIndex) {
            $this->hoveredIndex = $hitIndex;
            $this->redraw();
            $payload = $this->elementPayload($hitIndex, $x, $y);
            $this->emit('hoverchange', $payload);
        }

        // Always emit mousemove with current state
        $this->emit('mousemove', $this->elementPayload($hitIndex, $x, $y));

        // Button-down events
        if ($event->down !== 0) {
            $payload = $this->elementPayload($hitIndex, $x, $y);

            // Right-click
            if ($event->down === 2 || $event->down === 3) {
                $this->emit('contextmenu', $payload);
                return;
            }

            $this->emit('click', $payload);

            // Double-click
            if ($event->count === 2) {
                $this->emit('dblclick', $payload);
            }
        }
    }

    public function mouseCrossed(bool $left): void
    {
        if ($left) {
            $this->entered = false;
            $this->hoveredIndex = null;
            $this->emit('mouseleave', ['x' => 0, 'y' => 0, 'index' => null, 'element' => null, 'type' => null]);
        } else {
            $this->entered = true;
            $this->emit('mouseenter', ['x' => 0, 'y' => 0, 'index' => null, 'element' => null, 'type' => null]);
        }
    }

    /**
     * Find the topmost SVG element at (x, y). Checks elements in reverse
     * paint order (last drawn = topmost).
     *
     * Returns the element index, or null if no element was hit.
     */
    private function hitTest(float $x, float $y): ?int
    {
        for ($i = \count($this->elements) - 1; $i >= 0; $i--) {
            $el = $this->elements[$i];
            $b = $el['bounds'] ?? null;
            if ($b === null) {
                continue;
            }

            // Quick AABB reject
            if ($x < $b['minX'] || $x > $b['maxX'] || $y < $b['minY'] || $y > $b['maxY']) {
                continue;
            }

            // Precise hit-test for circles/ellipses
            $type = $el['type'] ?? 'path';
            if ($type === 'circle') {
                $dx = $x - $el['cx'];
                $dy = $y - $el['cy'];
                if ($dx * $dx + $dy * $dy <= $el['r'] * $el['r']) {
                    return $i;
                }
            } elseif ($type === 'ellipse') {
                $dx = ($x - $el['cx']) / $el['rx'];
                $dy = ($y - $el['cy']) / $el['ry'];
                if ($dx * $dx + $dy * $dy <= 1.0) {
                    return $i;
                }
            } else {
                // Paths, rects, lines, text — bounding box is sufficient
                return $i;
            }
        }

        return null;
    }

    /**
     * Build the standard event payload for a hit result.
     *
     * @return array{x: float, y: float, index: ?int, element: ?array, type: ?string}
     */
    private function elementPayload(?int $index, float $x, float $y): array
    {
        if ($index === null) {
            return ['x' => $x, 'y' => $y, 'index' => null, 'element' => null, 'type' => null];
        }

        $el = $this->elements[$index];
        return [
            'x' => $x,
            'y' => $y,
            'index' => $index,
            'element' => $el,
            'type' => $el['type'] ?? 'path',
        ];
    }

    /**
     * Convert SVG path d attribute to libui Path object.
     */
    private function svgPathToLibui(string $d): ?\Libui\Draw\Path
    {
        if ($d === '') {
            return null;
        }

        try {
            $svgPath = SVGPathData::fromString($d);
        } catch (\Throwable) {
            return null;
        }

        // Collect commands into array — library iterator returns them in REVERSE order
        $commands = [];
        foreach ($svgPath as $cmd) {
            $commands[] = $cmd;
        }
        $commands = \array_reverse($commands);

        $path = new \Libui\Draw\Path(DrawFillMode::Winding);

        foreach ($commands as $cmd) {
            $pts = $cmd->getPoints();
            $last = $cmd->getLastPoint();

            // Order matters: check subclasses BEFORE parent classes
            // Line extends Move, RelativeLine extends RelativeMove
            if ($cmd instanceof ClosePath) {
                $path->closeFigure();
            } elseif ($cmd instanceof Line) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof HorizontalLine) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof VerticalLine) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof RelativeLine) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof RelativeHorizontalLine) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof RelativeVerticalLine) {
                $path->lineTo($last[0], $last[1]);
            } elseif ($cmd instanceof BezierCurve) {
                $path->bezierTo($pts[0][0], $pts[0][1], $pts[1][0], $pts[1][1], $pts[2][0], $pts[2][1]);
            } elseif ($cmd instanceof RelativeBezierCurve) {
                $path->bezierTo($pts[0][0], $pts[0][1], $pts[1][0], $pts[1][1], $pts[2][0], $pts[2][1]);
            } elseif ($cmd instanceof QuadraticCurve) {
                $pts = $cmd->getPoints();
                $cp = $pts[0];
                $end = $pts[1];
                $prev = $cmd->getPrevious()->getLastPoint();
                $cp1x = $prev[0] + 2.0 / 3.0 * ($cp[0] - $prev[0]);
                $cp1y = $prev[1] + 2.0 / 3.0 * ($cp[1] - $prev[1]);
                $cp2x = $end[0] + 2.0 / 3.0 * ($cp[0] - $end[0]);
                $cp2y = $end[1] + 2.0 / 3.0 * ($cp[1] - $end[1]);
                $path->bezierTo($cp1x, $cp1y, $cp2x, $cp2y, $end[0], $end[1]);
            } elseif ($cmd instanceof RelativeQuadraticCurve) {
                $pts = $cmd->getPoints();
                $cp = $pts[0];
                $end = $pts[1];
                $prev = $cmd->getPrevious()->getLastPoint();
                $cp1x = $prev[0] + 2.0 / 3.0 * ($cp[0] - $prev[0]);
                $cp1y = $prev[1] + 2.0 / 3.0 * ($cp[1] - $prev[1]);
                $cp2x = $end[0] + 2.0 / 3.0 * ($cp[0] - $end[0]);
                $cp2y = $end[1] + 2.0 / 3.0 * ($cp[1] - $end[1]);
                $path->bezierTo($cp1x, $cp1y, $cp2x, $cp2y, $end[0], $end[1]);
            } elseif ($cmd instanceof ArcCurve || $cmd instanceof RelativeArcCurve) {
                $prev = $cmd->getPrevious()->getLastPoint();
                $this->arcToBeziers($path, $prev[0], $prev[1], $cmd->rx, $cmd->ry, $cmd->angle, $cmd->largeArcFlag, $cmd->sweepFlag, $last[0], $last[1]);
            } elseif ($cmd instanceof Move) {
                $path->newFigure($last[0], $last[1]);
            } elseif ($cmd instanceof RelativeMove) {
                $path->newFigure($last[0], $last[1]);
            }
        }

        $path->end();
        return $path;
    }

    /**
     * Convert SVG arc to cubic Bézier segments.
     *
     * Based on the SVG specification arc-to-bezier conversion algorithm.
     * Handles endpoint parameterization → center parameterization → Bézier approximation.
     */
    private function arcToBeziers(\Libui\Draw\Path $path, float $x0, float $y0, float $rx, float $ry, float $xAxisRotation, bool $largeArcFlag, bool $sweepFlag, float $x1, float $y1): void
    {
        $phi = deg2rad($xAxisRotation);
        $cosPhi = \cos($phi);
        $sinPhi = \sin($phi);

        $dx = ($x0 - $x1) / 2.0;
        $dy = ($y0 - $y1) / 2.0;

        $x1p = $cosPhi * $dx + $sinPhi * $dy;
        $y1p = -$sinPhi * $dx + $cosPhi * $dy;

        $rx = \abs($rx);
        $ry = \abs($ry);

        if ($rx == 0.0 || $ry == 0.0) {
            $path->lineTo($x1, $y1);
            return;
        }

        $lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
        if ($lambda > 1.0) {
            $scale = \sqrt($lambda);
            $rx *= $scale;
            $ry *= $scale;
        }

        $rx2 = $rx * $rx;
        $ry2 = $ry * $ry;
        $x1p2 = $x1p * $x1p;
        $y1p2 = $y1p * $y1p;

        $numerator = \sqrt(\max(0.0, $rx2 * $ry2 - $rx2 * $y1p2 - $ry2 * $x1p2));
        if ($largeArcFlag == $sweepFlag) {
            $numerator = -$numerator;
        }

        $cxp = $numerator * $rx * $y1p / $ry;
        $cyp = -$numerator * $ry * $x1p / $rx;

        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x0 + $x1) / 2.0;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y0 + $y1) / 2.0;

        $angle1 = $this->vecAngle(1.0, 0.0, ($x1p - $cxp) / $rx, ($y1p - $cyp) / $ry);
        $dangle = $this->vecAngle(($x1p - $cxp) / $rx, ($y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx, (-$y1p - $cyp) / $ry);

        if (!$sweepFlag && $dangle > 0) {
            $dangle -= 2.0 * M_PI;
        } elseif ($sweepFlag && $dangle < 0) {
            $dangle += 2.0 * M_PI;
        }

        $segments = (int) \ceil(\abs($dangle) / (M_PI / 2.0));
        $delta = $dangle / $segments;
        $t = 8.0 / 4.0 * \tan($delta / 4.0);

        for ($i = 0; $i < $segments; $i++) {
            $a = $angle1 + $i * $delta;
            $a1 = $a + $delta / 2.0;

            $t1x = $x0 - $t * ($y0 - $cy - $sinPhi * $rx * \sin($a) - $cosPhi * $ry * \cos($a));
            $t1y = $y0 + $t * ($x0 - $cx + $sinPhi * $ry * \cos($a) - $cosPhi * $rx * \sin($a));
            $t2x = $x1 + $t * ($y1 - $cy - $sinPhi * $rx * \sin($a + $delta) - $cosPhi * $ry * \cos($a + $delta));
            $t2y = $y1 - $t * ($x1 - $cx + $sinPhi * $ry * \cos($a + $delta) - $cosPhi * $rx * \sin($a + $delta));

            $path->bezierTo($t1x, $t1y, $t2x, $t2y, $x1, $y1);
        }
    }

    private function vecAngle(float $ux, float $uy, float $vx, float $vy): float
    {
        $n = \sqrt($ux * $ux + $uy * $uy) * \sqrt($vx * $vx + $vy * $vy);
        if ($n == 0.0) {
            return 0.0;
        }
        $dot = $ux * $vx + $uy * $vy;
        $cos = \max(-1.0, \min(1.0, $dot / $n));
        $angle = \acos($cos);
        if ($ux * $vy - $uy * $vx < 0.0) {
            $angle = -$angle;
        }
        return $angle;
    }
}
