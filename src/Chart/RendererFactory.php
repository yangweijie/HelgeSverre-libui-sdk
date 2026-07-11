<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * Maps a {@see ChartType} to its renderer.
 *
 * Registering a new chart kind is a two-step, isolated change: implement a
 * {@see ChartRenderer} and add it to {@see makeAll()} — the core {@see Chart}
 * never references concrete renderers directly.
 */
final class RendererFactory
{
    /** @var list<ChartRenderer>|null */
    private static ?array $registry = null;

    /** @return list<ChartRenderer> */
    public static function all(): array
    {
        if (self::$registry === null) {
            self::$registry = [
                new LineRenderer(),
                new BarRenderer(),
                new PieRenderer(),
            ];
        }

        return self::$registry;
    }

    public static function make(ChartType $type): ChartRenderer
    {
        foreach (self::all() as $renderer) {
            if ($renderer->supports($type)) {
                return $renderer;
            }
        }

        // Fallback: line knows how to draw most cartesian kinds.
        return new LineRenderer();
    }
}
