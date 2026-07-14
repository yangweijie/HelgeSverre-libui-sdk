<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Observability;

use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;
use Yangweijie\Ui2\Widgets\Surface;

/**
 * Runtime accessibility / automation snapshot of the current UI.
 *
 * Produces a SemanticsNode tree that an AI automation server can consume to
 * read the component tree, assert state, and (later) drive controls. The
 * snapshot is protocol-agnostic: an HTTP / WebSocket / MCP server would all
 * consume the same SemanticsNode contract, so none of this changes when the
 * transport is added.
 */
final class UiSnapshot
{
    /** Snapshot a Window or a self-drawn Surface (full semantics tree). */
    public static function capture(Surface|Window $target): SemanticsNode
    {
        return $target instanceof Surface
            ? self::fromSurface($target)
            : self::fromWindow($target);
    }

    public static function fromSurface(Surface $surface): SemanticsNode
    {
        return SemanticsNode::fromLayout($surface->rootLayout());
    }

    public static function fromWindow(Window $window): SemanticsNode
    {
        return $window->semantics()
            ?? new SemanticsNode(null, WidgetRole::Dialog);
    }

    /**
     * Snapshot a layout tree directly — headless-friendly, needs no libui
     * control. Handy for tests and for callers that already hold a LayoutNode.
     */
    public static function fromLayout(LayoutNode $root): SemanticsNode
    {
        return SemanticsNode::fromLayout($root);
    }

    /** @return array<string, mixed> */
    public static function toArray(Surface|Window $target): array
    {
        return self::capture($target)->toArray();
    }

    public static function toJson(Surface|Window $target, int $flags = 0): string
    {
        return self::capture($target)->toJson($flags);
    }
}
