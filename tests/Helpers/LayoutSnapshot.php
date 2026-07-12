<?php

declare(strict_types=1);

/**
 * Layout + rendering snapshot helper for headless UI testing.
 *
 * Builds a LayoutNode tree → computes layout → serializes tree structure
 * + geometry + per-leaf shape commands → compares against JSON baseline.
 *
 * Usage:
 *   LayoutSnapshot::assert('button-row', $root, 400, 50);
 *
 * First run creates baseline in tests/__snapshots__/.
 * Subsequent runs compare. Delete .snap to update.
 */

require_once __DIR__ . '/Snapshot.php';

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawText;
use Yangweijie\Ui2\Rendering\FillCircle;
use Yangweijie\Ui2\Rendering\FillPolygon;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\SaveClip;
use Yangweijie\Ui2\Rendering\StrokeArc;
use Yangweijie\Ui2\Rendering\StrokeCircle;
use Yangweijie\Ui2\Rendering\StrokeLine;
use Yangweijie\Ui2\Rendering\StrokeRoundedRect;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WidgetSpec;

final class LayoutSnapshot
{
    /**
     * Assert that a LayoutNode tree's layout + shape commands match the baseline.
     *
     * @param float $w Viewport width for layout
     * @param float $h Viewport height for layout
     */
    public static function assert(string $name, LayoutNode $root, float $w, float $h): void
    {
        FlexLayout::layout($root, 0, 0, $w, $h);

        $data = [
            'viewport' => ['w' => $w, 'h' => $h],
            'layout'   => self::serializeTree($root),
        ];

        Snapshot::assert('layout-' . $name, $data);
    }

    /**
     * Serialize a LayoutNode tree to a JSON-compatible array.
     * Includes: id, style, spec, computed geometry, and per-leaf shape commands.
     */
    private static function serializeTree(LayoutNode $node): array
    {
        $out = [];

        if ($node->id !== null) {
            $out['id'] = $node->id;
        }

        // Computed geometry
        $out['rect'] = [
            'x' => round($node->x, 2),
            'y' => round($node->y, 2),
            'w' => round($node->w, 2),
            'h' => round($node->h, 2),
        ];

        // Style (only non-default values)
        $s = $node->style;
        $style = [];
        if ($s->direction !== 'row') {
            $style['dir'] = $s->direction;
        }
        if ($s->gap !== 0.0) {
            $style['gap'] = $s->gap;
        }
        if ($s->padding !== 0.0) {
            $style['pad'] = $s->padding;
        }
        if ($s->justify !== 'start') {
            $style['justify'] = $s->justify;
        }
        if ($s->align !== 'stretch') {
            $style['align'] = $s->align;
        }
        if ($s->grow !== 0.0) {
            $style['grow'] = $s->grow;
        }
        if ($s->shrink !== 0.0) {
            $style['shrink'] = $s->shrink;
        }
        if ($s->width !== null) {
            $style['w'] = $s->width;
        }
        if ($s->height !== null) {
            $style['h'] = $s->height;
        }
        if (!empty($style)) {
            $out['style'] = $style;
        }

        // Widget spec
        if ($node->spec !== null) {
            $out['spec'] = self::serializeSpec($node->spec);
            $out['spec']['type'] = $node->spec->type();
        }

        // Interaction state (only if active — for snapshot determinism, skip default false)
        // pressed/hovered are transient, so we skip them in snapshots

        // Shape commands (headless-safe)
        if ($node->spec !== null && $node->w > 0 && $node->h > 0) {
            $registry = RendererRegistry::default();
            $renderer = $registry->get($node->spec->type());
            if ($renderer !== null) {
                $cmds = $renderer->shapeCommands($node->spec, new DesignTokens(), $node->w, $node->h);
                if (!empty($cmds)) {
                    $out['commands'] = array_map(self::serializeCommand(...), $cmds);
                }
            }
        }

        // Children
        if (!empty($node->children)) {
            $out['children'] = array_map(self::serializeTree(...), $node->children);
        }

        return $out;
    }

    /**
     * Serialize a WidgetSpec to a flat array of its constructor parameters.
     */
    private static function serializeSpec(WidgetSpec $spec): array
    {
        $refl = new ReflectionClass($spec);
        $props = $refl->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = [];
        foreach ($props as $prop) {
            $val = $prop->getValue($spec);
            // Round floats for snapshot stability
            if (is_float($val)) {
                $val = round($val, 4);
            }
            $data[$prop->getName()] = $val;
        }

        return $data;
    }

    /**
     * Serialize a RenderCommand to a JSON-compatible array.
     * Skips DrawText (contains native TextLayout) and SaveClip (recursive).
     */
    private static function serializeCommand(RenderCommand $cmd): array
    {
        $class = (new ReflectionClass($cmd))->getShortName();
        $props = (new ReflectionClass($cmd))->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = ['_cmd' => $class];
        foreach ($props as $prop) {
            $val = $prop->getValue($cmd);
            if ($val instanceof \Libui\Color) {
                $val = [
                    'r' => round($val->r, 4),
                    'g' => round($val->g, 4),
                    'b' => round($val->b, 4),
                    'a' => round($val->a, 4),
                ];
            } elseif ($val instanceof \Libui\Draw\StrokeParams) {
                $val = [
                    'cap'    => $val->cap->value,
                    'join'   => $val->join->value,
                    'miterLimit' => $val->miterLimit,
                    'dashes' => $val->dashes,
                    'thickness' => $val->thickness,
                ];
            } elseif (is_float($val)) {
                $val = round($val, 4);
            } elseif ($val instanceof \Libui\Text\TextLayout) {
                $val = '(TextLayout)';
            } elseif ($val instanceof \Libui\Draw\Path) {
                $val = '(Path)';
            } elseif (is_array($val)) {
                // FillPolygon points array
                $val = array_map(fn ($p) => is_object($p) ? ['x' => round($p->x, 4), 'y' => round($p->y, 4)] : $p, $val);
            }
            $data[$prop->getName()] = $val;
        }

        return $data;
    }
}
