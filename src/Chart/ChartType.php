<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Chart;

/**
 * Supported chart kinds. The renderer factory maps each to a concrete renderer.
 * Mixed charts (e.g. a bar + line on the same axes) are possible by setting a
 * per-dataset {@see Dataset::$type} override; the cartesian renderer dispatches
 * each series to its own draw routine.
 */
enum ChartType: string
{
    case Line = 'line';
    case Bar = 'bar';
    case Pie = 'pie';
    case Doughnut = 'doughnut';
    case Scatter = 'scatter';

    /** Whether this kind is drawn on a cartesian (x/y) coordinate system. */
    public function isCartesian(): bool
    {
        return $this !== self::Pie && $this !== self::Doughnut;
    }
}
