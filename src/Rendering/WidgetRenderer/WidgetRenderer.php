<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Turns a {@see WidgetSpec} + {@see DesignTokens} into a retained-mode
 * {@see RenderCommandList} for one frame.
 *
 * This is the L3 declarative entry point: a widget collects its current state
 * into a spec, asks the registry for the matching renderer, and the renderer
 * emits the drawing commands. Keeping the renderer separate from the control
 * means theming, geometry, and the command model are shared and testable in
 * isolation — the control only owns "when to repaint" and "what state I'm in".
 */
interface WidgetRenderer
{
    /**
     * The widget type this renderer serves; must match the spec's type().
     */
    public static function type(): string;

    /**
     * Pure geometry only — no TextLayout — so it is safe to call headlessly.
     *
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array;

    /**
     * Full frame: geometry plus any text. Building text requires a live libui
     * context (TextLayout), so this path is verified in the GUI demo while
     * shapeCommands() carries the headless unit coverage.
     */
    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList;
}
