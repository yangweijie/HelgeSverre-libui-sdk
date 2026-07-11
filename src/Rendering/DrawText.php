<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Text\TextLayout;

/**
 * A laid-out text block, drawn with its top-left corner at ($x, $y).
 *
 * The TextLayout is owned by this command and must be freed via
 * RenderCommandList::free() when the list is discarded or the widget repaints —
 * otherwise the next frame would draw into an already-freed native layout.
 */
final class DrawText extends RenderCommand
{
    public function __construct(
        public TextLayout $layout,
        public float $x,
        public float $y,
    ) {
    }
}
