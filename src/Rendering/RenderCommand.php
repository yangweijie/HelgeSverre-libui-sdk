<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

/**
 * Base class for retained-mode render commands.
 *
 * A widget compiles its visual state into a list of these (see
 * RenderCommandList), and a CommandExecutor translates them into
 * immediate-mode DrawContext calls. Keeping the description separate from
 * execution is what enables batching, caching, and (later) record/replay —
 * mirroring Native SDK's command model, but expressed in idiomatic PHP
 * (a class hierarchy rather than a Rust-style data enum).
 *
 * Each concrete command lives in its own file so PSR-4 autoloading resolves it
 * by class name (e.g. StrokeArc → src/Rendering/StrokeArc.php).
 */
abstract class RenderCommand
{
}
