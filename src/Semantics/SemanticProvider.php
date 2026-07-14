<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Semantics;

/**
 * Anything that can contribute a node to the accessibility / automation tree.
 *
 * Implemented by every libui Control (via the Libui\Control base) and by the
 * self-drawn Surface, so an automation server can walk the tree uniformly by
 * calling semantics() recursively — no per-widget branching in callers.
 */
interface SemanticProvider
{
    /** Return this node's semantics, or null when it contributes nothing. */
    public function semantics(): ?SemanticsNode;
}
