<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Draw\Brush;
use Libui\Draw\DrawContext;
use Libui\Draw\Path;

/**
 * Translates a RenderCommandList into immediate-mode DrawContext calls.
 *
 * Pure translation: no visual logic lives here. Widgets decide *what* to draw;
 * the executor only knows *how* each command maps to the canvas. This is the
 * single place where the retained command model meets libui's immediate-mode
 * DrawContext.
 */
final class CommandExecutor
{
    public function execute(DrawContext $ctx, RenderCommandList $list): void
    {
        foreach ($list->commands as $cmd) {
            $this->dispatch($ctx, $cmd);
        }
    }

    private function dispatch(DrawContext $ctx, RenderCommand $cmd): void
    {
        match (true) {
            $cmd instanceof StrokeArc => $ctx->strokePath(
                Brush::color($cmd->color),
                $cmd->stroke,
                static fn (Path $p) => $p->arc(
                    $cmd->cx,
                    $cmd->cy,
                    $cmd->radius,
                    $cmd->startAngle,
                    $cmd->sweep,
                ),
            ),
            $cmd instanceof FillRoundedRect => $ctx->fillRoundedRect(
                $cmd->x,
                $cmd->y,
                $cmd->width,
                $cmd->height,
                $cmd->radius,
                $cmd->color,
            ),
            $cmd instanceof StrokeRoundedRect => $ctx->strokeRoundedRect(
                $cmd->x,
                $cmd->y,
                $cmd->width,
                $cmd->height,
                $cmd->radius,
                $cmd->color,
                $cmd->stroke,
            ),
            $cmd instanceof FillCircle => $ctx->fillCircle(
                $cmd->cx,
                $cmd->cy,
                $cmd->radius,
                $cmd->color,
            ),
            $cmd instanceof StrokeCircle => $ctx->strokeCircle(
                $cmd->cx,
                $cmd->cy,
                $cmd->radius,
                $cmd->color,
                $cmd->stroke,
            ),
            $cmd instanceof DrawText => $ctx->text($cmd->layout, $cmd->x, $cmd->y),
            $cmd instanceof StrokeLine => $ctx->line(
                $cmd->x0,
                $cmd->y0,
                $cmd->x1,
                $cmd->y1,
                $cmd->color,
                $cmd->thickness,
            ),
            $cmd instanceof FillPolygon => $ctx->fillPolygon(
                $cmd->points,
                $cmd->color,
            ),
            $cmd instanceof SaveClip => $ctx->withSave(
                function (DrawContext $c) use ($cmd): void {
                    $c->clip($cmd->path);
                    $this->execute($c, new RenderCommandList($cmd->children));
                },
            ),
        };
    }
}
