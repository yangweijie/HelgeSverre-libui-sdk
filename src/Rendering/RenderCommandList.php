<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

/**
 * An ordered list of render commands produced by a widget for a single frame.
 *
 * The list owns any native resources referenced by its commands (TextLayouts,
 * clipped Paths). Call free() at the end of each draw, after execution, or when
 * discarding a retained list — PHP's GC timing inside FFI callbacks is
 * unpredictable, so explicit freeing is required (see DrawContext::drawString).
 */
final class RenderCommandList
{
    /** @param RenderCommand[] $commands */
    public function __construct(
        public readonly array $commands,
    ) {
    }

    public function free(): void
    {
        foreach ($this->commands as $cmd) {
            if ($cmd instanceof DrawText) {
                $cmd->layout->free();
            } elseif ($cmd instanceof SaveClip) {
                (new self($cmd->children))->free();
                $cmd->path->free();
            }
        }
    }
}
