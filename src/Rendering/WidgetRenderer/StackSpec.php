<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Layout container whose children are stacked on top of each other.
 * Purely structural — no visual rendering. Children are positioned at
 * the same origin and overlay each other (z-order follows tree order).
 */
final class StackSpec extends WidgetSpec
{
    public function __construct()
    {
    }

    public function type(): string
    {
        return 'stack';
    }
}
