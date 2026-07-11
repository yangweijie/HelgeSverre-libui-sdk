<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * A declarative description of a widget's visual state.
 *
 * Renderers are keyed by the discriminator returned by {@see type()}, which
 * must match the string a {@see WidgetRenderer} advertises via its own
 * type(). This is the "what to draw" half of the L3 declarative widget layer —
 * the renderer turns a spec + tokens into a {@see \Yangweijie\Ui2\Rendering\RenderCommandList}.
 *
 * Specs are immutable value objects so a single renderer instance can be reused
 * across frames and across many widgets.
 */
abstract class WidgetSpec
{
    /**
     * Discriminator matching the renderer registered in {@see RendererRegistry}.
     */
    abstract public function type(): string;
}
