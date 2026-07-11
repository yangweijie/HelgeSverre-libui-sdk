<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Draw\Path;

/**
 * A clip command expressed as a tree node: pushes a save scope, intersects the
 * clip region with $path, recursively executes $children, then restores. This
 * makes push/pop always pair, so a flat command array can still express nested
 * clipping without separate PopClip commands.
 *
 * @property RenderCommand[] $children
 */
final class SaveClip extends RenderCommand
{
    /** @param RenderCommand[] $children */
    public function __construct(
        public Path $path,
        public array $children,
    ) {
    }
}
