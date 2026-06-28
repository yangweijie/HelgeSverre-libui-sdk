<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\AreaDelegate;
use Libui\Color;
use Libui\Control;
use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\StrokeParams;
use Libui\Generated\Enum\DrawFillMode;
use Libui\Generated\Enum\DrawLineCap;
use Libui\Generated\Enum\DrawLineJoin;
use Libui\Text\AttributedString;
use Libui\Text\Attribute;
use Libui\Text\FontDescriptor;
use Libui\Text\TextLayout;
use Libui\Generated\Enum\DrawTextAlign;
use Yangweijie\Ui2\Composite;
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
 * SVG display widget — renders SVG path data using libui's Area + DrawContext.
 *
 * Supports: paths with fill/stroke, solid colors, opacity.
 * Limitations: no gradients (url(#...)), no CSS inheritance, no dash arrays.
 *
 * ```php
 * $svg = new SvgView(400, 300);
 * $svg->loadFile('icon.svg');
 * // or
 * $svg->loadString('<svg>...</svg>');
 * ```
 */
class SvgView extends Composite
{
    private readonly Area $area;
    private readonly SvgDelegate $delegate;

    public function __construct(int $width = 200, int $height = 200)
    {
        $this->delegate = new SvgDelegate();
        $this->delegate->width = $width;
        $this->delegate->height = $height;
        $this->area = Area::scrolling($this->delegate, $width, $height);
        $this->area->setSize($width, $height);

        // Force redraw after layout — scrolling Area needs time to initialize
        $area = $this->area;
        \Libui\Ffi::timer(100, function () use ($area): bool {
            $area->setSize(300, 300);
            $area->queueRedrawAll();
            return false;
        });
    }

    public function root(): Control
    {
        return $this->area;
    }

    /**
     * Load SVG from a file path.
     */
    public function loadFile(string $path): static
    {
        if (!\file_exists($path)) {
            throw new \RuntimeException("SVG file not found: {$path}");
        }
        $this->loadString(\file_get_contents($path));
        return $this;
    }

    /**
     * Load SVG from a string.
     */
    public function loadString(string $svgContent): static
    {
        $this->delegate->parse($svgContent);
        $this->area->queueRedrawAll();
        return $this;
    }

    /**
     * Load raw path data (one or more `d` attribute strings).
     */
    public function loadPaths(array $paths): static
    {
        $this->delegate->setPaths($paths);
        $this->area->queueRedrawAll();
        return $this;
    }
}

/**
 * @internal Area delegate for SVG rendering.
 */
final class SvgDelegate extends AreaDelegate
{
    /** @var list<array{d: string, fill: ?string, stroke: ?string, strokeWidth: float, opacity: float}> */
    private array $elements = [];

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

        $this->elements[] = ['d' => $d, 'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
    }

    /**
     * @param \SimpleXMLElement $el
     */
    private function addCircle(\SimpleXMLElement $el, ?string $fill, ?string $stroke, float $strokeWidth, float $opacity): void
    {
        $cx = (float) ($el['cx'] ?? 0);
        $cy = (float) ($el['cy'] ?? 0);
        $r = (float) ($el['r'] ?? 0);
        $this->elements[] = ['type' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $r, 'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
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
        $this->elements[] = ['type' => 'ellipse', 'cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry, 'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
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
        $this->elements[] = ['d' => $d, 'fill' => null, 'stroke' => $stroke ?? '#000', 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
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
        $this->elements[] = ['d' => $d, 'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
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
        $this->elements[] = ['d' => $d, 'fill' => null, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'opacity' => $opacity];
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
        $this->elements[] = ['type' => 'text', 'text' => $text, 'x' => $x + $dx, 'y' => $y + $dy, 'fontSize' => $fontSize, 'fill' => $fill, 'opacity' => $opacity];
    }

    public function setPaths(array $paths): void
    {
        $this->elements = [];
        foreach ($paths as $d) {
            $this->elements[] = ['d' => $d, 'fill' => '#000', 'stroke' => null, 'strokeWidth' => 1.0, 'opacity' => 1.0];
        }
    }

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        foreach ($this->elements as $el) {
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
                $font = new \Libui\Text\FontDescriptor('sans-serif', $el['fontSize']);
                $color = $fill !== null ? $this->parseColor($el['fill'], $el['opacity']) : \Libui\Color::rgba(0, 0, 0, 1.0);
                $ctx->drawString($el['text'], $font, $color, $el['x'], $el['y']);
            }
        }
    }

    private function parseColor(string $color, float $opacity): \Libui\Color
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
            return \Libui\Color::rgba($r, $g, $b, $a * $opacity);
        }

        return \Libui\Color::rgba(0, 0, 0, $opacity);
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
