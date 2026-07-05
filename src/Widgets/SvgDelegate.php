<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\AreaDelegate;
use Libui\Color;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
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
 */
final class SvgDelegate extends AreaDelegate
{
    use EmitsEvents;

    /** @var list<array{d?: string, fill: ?string, stroke: ?string, strokeWidth: float, opacity: float, bounds?: array}> */
    private array $elements = [];

    /** Index of the element currently under the mouse cursor, or -1. */
    private int $hoveredIndex = -1;

    /** Whether the mouse is currently inside the SVG area. */
    private bool $entered = false;

    public int $width = 200;
    public int $height = 200;

    public function parse(string $svgContent): void
    {
        $this->elements = [];
        $xml = @\simplexml_load_string($svgContent);
        if ($xml === false) {
            return;
        }

        // Get SVG dimensions
        $this->width = (int) ($xml['width'] ?? 200);
        $this->height = (int) ($xml['height'] ?? 200);

        // Register namespace for XPath
        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');

        // Parse all elements that have visual attributes
        $this->parseElements($xml);
    }

    /**
     * @param \SimpleXMLElement $xml
     */
    private function parseElements(\SimpleXMLElement $xml, ?string $inheritedFill = null, ?string $inheritedStroke = null, float $inheritedStrokeWidth = 1.0, float $inheritedOpacity = 1.0): void
    {
        foreach ($xml->children() as $child) {
            $name = $child->getName();
            $attrs = $child->attributes();

            $fill = isset($attrs['fill']) ? (string) $attrs['fill'] : $inheritedFill;
            $stroke = isset($attrs['stroke']) ? (string) $attrs['stroke'] : $inheritedStroke;
            $strokeWidth = isset($attrs['stroke-width']) ? (float) $attrs['stroke-width'] : $inheritedStrokeWidth;
            $opacity = isset($attrs['opacity']) ? (float) $attrs['opacity'] : $inheritedOpacity;

            // Check style attribute for color overrides
            $style = isset($attrs['style']) ? (string) $attrs['style'] : '';
            if ($style !== '') {
                $fill = $this->parseStyle($style, 'fill', $fill);
                $stroke = $this->parseStyle($style, 'stroke', $stroke);
            }

            match ($name) {
                'path' => $this->addElement($child, $fill, $stroke, $strokeWidth, $opacity),
                'rect' => $this->addRect($child, $fill, $stroke, $strokeWidth, $opacity),
                'circle' => $this->addCircle($child, $fill, $stroke, $strokeWidth, $opacity),
                'ellipse' => $this->addEllipse($child, $fill, $stroke, $strokeWidth, $opacity),
                'line' => $this->addLine($child, $stroke, $strokeWidth, $opacity),
                'polygon' => $this->addPolygon($child, $fill, $stroke, $strokeWidth, $opacity),
                'polyline' => $this->addPolyline($child, $fill, $stroke, $strokeWidth, $opacity),
                'text' => $this->addText($child, $fill, $opacity),
                'g' => $this->parseGroup($child, $fill, $stroke, $strokeWidth, $opacity),
                default => null,
            };
        }
    }

    /**
     * @param \SimpleXMLElement $xml
     */
    private function parseGroup(\SimpleXMLElement $xml, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $attrs = $xml->attributes();
        $fill = isset($attrs['fill']) ? (string) $attrs['fill'] : $fill;
        $stroke = isset($attrs['stroke']) ? (string) $attrs['stroke'] : $stroke;
        $strokeWidth = isset($attrs['stroke-width']) ? (float) $attrs['stroke-width'] : $strokeWidth;
        $opacity = isset($attrs['opacity']) ? (float) $attrs['opacity'] : $opacity;

        $this->parseElements($xml, $fill, $stroke, $strokeWidth, $opacity);
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

    private function parseStyle(string $style, string $prop, ?string $fallback): ?string
    {
        if (preg_match("/{$prop}\s*:\s*([^;]+)/", $style, $m)) {
            return trim($m[1]);
        }
        return $fallback;
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addElement(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $d = (string) ($el['d'] ?? '');
        if ($d === '') {
            return;
        }
        $this->elements[] = [
            'd' => $d,
            'fill' => $fill,
            'stroke' => $stroke,
            'strokeWidth' => $strokeWidth,
            'opacity' => $opacity,
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addRect(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $w = (float) ($el['width'] ?? 0);
        $h = (float) ($el['height'] ?? 0);
        $rx = (float) ($el['rx'] ?? 0);
        $ry = (float) ($el['ry'] ?? $rx);

        $d = "M {$x} {$y} L " . ($x + $w) . " {$y} L " . ($x + $w) . " " . ($y + $h) . " L {$x} " . ($y + $h) . " Z";

        $this->elements[] = [
            'd' => $d, 'fill' => $fill, 'stroke' => $stroke,
            'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => ['minX' => $x - 1, 'minY' => $y - 1, 'maxX' => $x + $w + 1, 'maxY' => $y + $h + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addCircle(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $cx = (float) ($el['cx'] ?? 0);
        $cy = (float) ($el['cy'] ?? 0);
        $r = (float) ($el['r'] ?? 0);
        $this->elements[] = [
            'type' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $r,
            'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => ['minX' => $cx - $r - 1, 'minY' => $cy - $r - 1, 'maxX' => $cx + $r + 1, 'maxY' => $cy + $r + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addEllipse(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $cx = (float) ($el['cx'] ?? 0);
        $cy = (float) ($el['cy'] ?? 0);
        $rx = (float) ($el['rx'] ?? 0);
        $ry = (float) ($el['ry'] ?? 0);
        $this->elements[] = [
            'type' => 'ellipse', 'cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry,
            'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => ['minX' => $cx - $rx - 1, 'minY' => $cy - $ry - 1, 'maxX' => $cx + $rx + 1, 'maxY' => $cy + $ry + 1],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addLine(\SimpleXMLElement $el, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $x1 = (float) ($el['x1'] ?? 0);
        $y1 = (float) ($el['y1'] ?? 0);
        $x2 = (float) ($el['x2'] ?? 0);
        $y2 = (float) ($el['y2'] ?? 0);
        $d = "M {$x1} {$y1} L {$x2} {$y2}";
        $minX = \min($x1, $x2) - 1; $maxX = \max($x1, $x2) + 1;
        $minY = \min($y1, $y2) - 1; $maxY = \max($y1, $y2) + 1;
        $this->elements[] = [
            'd' => $d, 'fill' => null, 'stroke' => $stroke ?? '#000',
            'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => ['minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY],
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addPolygon(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
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
            'd' => $d, 'fill' => $fill, 'stroke' => $stroke,
            'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addPolyline(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
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
            'd' => $d, 'fill' => null, 'stroke' => $stroke,
            'strokeWidth' => $strokeWidth, 'opacity' => $opacity,
            'bounds' => $this->pathBounds($d),
        ];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addText(\SimpleXMLElement $el, ?string $fill, float $opacity): void
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
            'fontSize' => $fontSize, 'fill' => $fill, 'opacity' => $opacity,
            'bounds' => ['minX' => $tx - 1, 'minY' => $ty - $fontSize - 1, 'maxX' => $tx + $approxWidth + 1, 'maxY' => $ty + 4],
        ];
    }

    public function setPaths(array $paths): void
    {
        $this->elements = [];
        $this->hoveredIndex = -1;
        foreach ($paths as $d) {
            $this->elements[] = [
                'd' => $d, 'fill' => '#000', 'stroke' => null,
                'strokeWidth' => 1.0, 'opacity' => 1.0,
                'bounds' => $this->pathBounds($d),
            ];
        }
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        foreach ($this->elements as $i => $el) {
            $type = $el['type'] ?? 'path';
            $fill = ($el['fill'] ?? 'none') !== 'none' ? $this->makeBrush($el['fill'] ?? '#000', $el['opacity'] ?? 1.0) : null;
            $stroke = ($el['stroke'] ?? 'none') !== 'none' ? $this->makeBrush($el['stroke'] ?? '#000', $el['opacity'] ?? 1.0) : null;
            $sw = $el['strokeWidth'] ?? 1.0;

            if ($type === 'rect') {
                $p = new \Libui\Draw\Path();
                $p->addRectangle($el['x'], $el['y'], $el['w'], $el['h']);
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, new StrokeParams(thickness: $sw));
                $p->free();
            } elseif ($type === 'circle') {
                $p = new \Libui\Draw\Path();
                $p->newFigureWithArc($el['cx'], $el['cy'], $el['r'], 0, 2 * M_PI);
                $p->closeFigure();
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, new StrokeParams(thickness: $sw));
                $p->free();
            } elseif ($type === 'ellipse') {
                $p = new \Libui\Draw\Path();
                $p->ellipse($el['cx'], $el['cy'], $el['rx'], $el['ry']);
                $p->end();
                if ($fill) $ctx->fill($p, $fill);
                if ($stroke) $ctx->stroke($p, $stroke, new StrokeParams(thickness: $sw));
                $p->free();
            } elseif ($type === 'line') {
                $p = new \Libui\Draw\Path();
                $p->newFigure($el['x1'], $el['y1']);
                $p->lineTo($el['x2'], $el['y2']);
                $p->end();
                if ($stroke) $ctx->stroke($p, $stroke, new StrokeParams(thickness: $sw));
                $p->free();
            } elseif ($type === 'path' && isset($el['d'])) {
                $path = $this->svgPathToLibui($el['d']);
                if ($path === null) continue;
                if ($fill) $ctx->fill($path, $fill);
                if ($stroke) $ctx->stroke($path, $stroke, new StrokeParams(thickness: $sw));
                $path->free();
            } elseif ($type === 'text') {
                $font = new FontDescriptor('sans-serif', $el['fontSize']);
                $color = $fill !== null ? $this->parseColor($el['fill'], $el['opacity']) : Color::rgba(0, 0, 0, 1.0);
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

    private function parseColor(string $color, float $opacity): Color
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
            return Color::rgba($r, $g, $b, $a * $opacity);
        }

        return Color::rgba(0, 0, 0, $opacity);
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
            $this->hoveredIndex = -1;
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

    private function makeBrush(string $color, float $opacity): Brush
    {
        $color = \strtolower(\trim($color));

        $named = match ($color) {
            'black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000',
            'green' => '#008000', 'blue' => '#0000ff', 'yellow' => '#ffff00',
            'cyan' => '#00ffff', 'magenta' => '#ff00ff', 'gray', 'grey' => '#808080',
            'orange' => '#ffa500', 'purple' => '#800080', 'pink' => '#ffc0cb',
            'brown' => '#a52a2a', default => $color,
        };

        // Parse hex color
        if (\preg_match('/^#([0-9a-f]{3,8})$/', $named, $m)) {
            $hex = $m[1];
            if (\strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $r = \hexdec(\substr($hex, 0, 2)) / 255.0;
            $g = \hexdec(\substr($hex, 2, 2)) / 255.0;
            $b = \hexdec(\substr($hex, 4, 2)) / 255.0;
            $a = isset($hex[6]) ? \hexdec(\substr($hex, 6, 2)) / 255.0 : 1.0;
            return Brush::color(Color::rgba($r, $g, $b, $a * $opacity));
        }

        // Parse rgb(r,g,b)
        if (\preg_match('/rgb\((\d+),\s*(\d+),\s*(\d+)\)/', $named, $m)) {
            return Brush::color(Color::rgba((int) $m[1] / 255.0, (int) $m[2] / 255.0, (int) $m[3] / 255.0, $opacity));
        }

        // Parse rgba(r,g,b,a)
        if (\preg_match('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $named, $m)) {
            return Brush::color(Color::rgba((int) $m[1] / 255.0, (int) $m[2] / 255.0, (int) $m[3] / 255.0, (float) $m[4] * $opacity));
        }

        // Fallback: black
        return Brush::color(Color::rgba(0, 0, 0, $opacity));
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
