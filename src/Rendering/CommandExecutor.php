<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

use Libui\Color;
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
            $cmd instanceof FillArc => $ctx->fillPath(
                Brush::color($cmd->color),
                static fn (Path $p) => $cmd->wedge
                    ? $p->wedge($cmd->cx, $cmd->cy, $cmd->radius, $cmd->startAngle, $cmd->sweep)
                    : $p->arc($cmd->cx, $cmd->cy, $cmd->radius, $cmd->startAngle, $cmd->sweep),
            ),
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
            $cmd instanceof DrawImage => $this->drawImage($ctx, $cmd),
        };
    }

    /**
     * Compute the destination and source rectangles for a DrawImage command.
     *
     * @return array{float,float,float,float,float,float,float,float}
     *     [destX, destY, destW, destH, srcX, srcY, srcW, srcH]
     */
    private function computeImageRects(DrawImage $cmd): array
    {
        $dstW = $cmd->drawW;
        $dstH = $cmd->drawH;
        $srcPixelW = (float) $cmd->imgW;
        $srcPixelH = (float) $cmd->imgH;

        return match ($cmd->fit) {
            DrawImage::FIT_STRETCH => [
                $cmd->x, $cmd->y, $dstW, $dstH,
                0.0, 0.0, $srcPixelW, $srcPixelH,
            ],
            DrawImage::FIT_CONTAIN => $this->containRects($cmd, $dstW, $dstH, $srcPixelW, $srcPixelH),
            DrawImage::FIT_COVER => $this->coverRects($cmd, $dstW, $dstH, $srcPixelW, $srcPixelH),
            default => [
                $cmd->x, $cmd->y, $dstW, $dstH,
                0.0, 0.0, $srcPixelW, $srcPixelH,
            ],
        };
    }

    /**
     * @return array{float,float,float,float,float,float,float,float}
     */
    private function containRects(DrawImage $cmd, float $dstW, float $dstH, float $sw, float $sh): array
    {
        $aspect = $sw / $sh;
        $dstAspect = $dstW / $dstH;

        if ($aspect >= $dstAspect) {
            $dw = $dstW;
            $dh = $dstW / $aspect;
        } else {
            $dh = $dstH;
            $dw = $dstH * $aspect;
        }

        return [
            $cmd->x + ($dstW - $dw) / 2, $cmd->y + ($dstH - $dh) / 2, $dw, $dh,
            0.0, 0.0, $sw, $sh,
        ];
    }

    /**
     * @return array{float,float,float,float,float,float,float,float}
     */
    private function coverRects(DrawImage $cmd, float $dstW, float $dstH, float $sw, float $sh): array
    {
        $aspect = $sw / $sh;
        $dstAspect = $dstW / $dstH;

        if ($aspect >= $dstAspect) {
            $cropW = $sh * $dstAspect;
            $cropH = $sh;
        } else {
            $cropW = $sw;
            $cropH = $sw / $dstAspect;
        }

        return [
            $cmd->x, $cmd->y, $dstW, $dstH,
            ($sw - $cropW) / 2, ($sh - $cropH) / 2, $cropW, $cropH,
        ];
    }

    /**
     * Draw a bitmap with scaling, fit modes, sampling, and optional rounded clip.
     */
    private function drawImage(DrawContext $ctx, DrawImage $cmd): void
    {
        [$dstX, $dstY, $dstW, $dstH, $srcX, $srcY, $srcW, $srcH] = $this->computeImageRects($cmd);

        if ($cmd->radius > 0.0) {
            $clip = (new Path())->roundedRect($cmd->x, $cmd->y, $cmd->drawW, $cmd->drawH, $cmd->radius)->end();
            $ctx->withSave(function (DrawContext $c) use ($clip, $cmd, $dstX, $dstY, $dstW, $dstH, $srcX, $srcY, $srcW, $srcH): void {
                $c->clip($clip);
                $this->drawSampledPixels($c, $cmd->pixels, $cmd->imgW, $cmd->imgH, $dstX, $dstY, $dstW, $dstH, $srcX, $srcY, $srcW, $srcH, $cmd->sampling);
            });
        } else {
            $this->drawSampledPixels($ctx, $cmd->pixels, $cmd->imgW, $cmd->imgH, $dstX, $dstY, $dstW, $dstH, $srcX, $srcY, $srcW, $srcH, $cmd->sampling);
        }
    }

    /**
     * Sample a pixel from the source using nearest-neighbour interpolation.
     *
     * @param float[] $pixels
     * @return array{float,float,float,float} [r, g, b, a]
     */
    private function sampleNearest(array $pixels, int $imgW, int $imgH, float $sx, float $sy): array
    {
        $ix = (int) round($sx);
        $iy = (int) round($sy);
        $ix = max(0, min($imgW - 1, $ix));
        $iy = max(0, min($imgH - 1, $iy));
        $idx = ($iy * $imgW + $ix) * 4;

        return [$pixels[$idx], $pixels[$idx + 1], $pixels[$idx + 2], $pixels[$idx + 3]];
    }

    /**
     * Bilinear interpolation between the four nearest source pixels.
     *
     * @param float[] $pixels
     * @return array{float,float,float,float} [r, g, b, a]
     */
    private function sampleLinear(array $pixels, int $imgW, int $imgH, float $sx, float $sy): array
    {
        $x1 = max(0, min($imgW - 1, (int) floor($sx)));
        $y1 = max(0, min($imgH - 1, (int) floor($sy)));
        $x2 = min($x1 + 1, $imgW - 1);
        $y2 = min($y1 + 1, $imgH - 1);
        $fx = $sx - $x1;
        $fy = $sy - $y1;

        $tl = ($y1 * $imgW + $x1) * 4;
        $tr = ($y1 * $imgW + $x2) * 4;
        $bl = ($y2 * $imgW + $x1) * 4;
        $br = ($y2 * $imgW + $x2) * 4;

        $wTL = (1 - $fx) * (1 - $fy);
        $wTR = $fx * (1 - $fy);
        $wBL = (1 - $fx) * $fy;
        $wBR = $fx * $fy;

        return [
            $pixels[$tl] * $wTL + $pixels[$tr] * $wTR + $pixels[$bl] * $wBL + $pixels[$br] * $wBR,
            $pixels[$tl + 1] * $wTL + $pixels[$tr + 1] * $wTR + $pixels[$bl + 1] * $wBL + $pixels[$br + 1] * $wBR,
            $pixels[$tl + 2] * $wTL + $pixels[$tr + 2] * $wTR + $pixels[$bl + 2] * $wBL + $pixels[$br + 2] * $wBR,
            $pixels[$tl + 3] * $wTL + $pixels[$tr + 3] * $wTR + $pixels[$bl + 3] * $wBL + $pixels[$br + 3] * $wBR,
        ];
    }

    /**
     * Render sampled (scaled) pixels to the destination rect with RLE merging.
     *
     * @param float[] $pixels Source RGBA pixel array.
     */
    private function drawSampledPixels(
        DrawContext $ctx,
        array $pixels,
        int $imgW,
        int $imgH,
        float $dstX,
        float $dstY,
        float $dstW,
        float $dstH,
        float $srcX,
        float $srcY,
        float $srcW,
        float $srcH,
        string $sampling,
    ): void {
        $idstW = (int) ceil($dstW);
        $idstH = (int) ceil($dstH);

        // Snap the destination rectangle to integer device pixels so each
        // filled run maps exactly to physical pixels; this avoids sub-pixel
        // gaps/overlaps that otherwise produce bright or dark artifacts.
        $originX = (float) round($dstX);
        $originY = (float) round($dstY);

        for ($row = 0; $row < $idstH; $row++) {
            $runStart = -1;
            $runR = 0.0;
            $runG = 0.0;
            $runB = 0.0;
            $runA = 0.0;
            $runQR = 0;
            $runQG = 0;
            $runQB = 0;
            $runQA = 0;

            for ($col = 0; $col < $idstW; $col++) {
                $sx = $srcX + ($col / $dstW) * $srcW;
                $sy = $srcY + ($row / $dstH) * $srcH;

                [$r, $g, $b, $a] = $sampling === DrawImage::SAMPLE_NEAREST
                    ? $this->sampleNearest($pixels, $imgW, $imgH, $sx, $sy)
                    : $this->sampleLinear($pixels, $imgW, $imgH, $sx, $sy);

                // Quantize to 8-bit sRGB for run comparison so tiny floating
                // point differences (from bilinear weights) do not break runs.
                $qR = (int) round($r * 255.0);
                $qG = (int) round($g * 255.0);
                $qB = (int) round($b * 255.0);
                $qA = (int) round($a * 255.0);

                if ($qA <= 0) {
                    if ($runStart >= 0) {
                        $ctx->fillRect($originX + $runStart, $originY + $row, $col - $runStart, 1.0, Color::rgba($runR, $runG, $runB, $runA));
                        $runStart = -1;
                    }
                    continue;
                }

                if ($runStart < 0) {
                    $runStart = $col;
                    $runR = $r;
                    $runG = $g;
                    $runB = $b;
                    $runA = $a;
                    $runQR = $qR;
                    $runQG = $qG;
                    $runQB = $qB;
                    $runQA = $qA;
                } elseif ($qR !== $runQR || $qG !== $runQG || $qB !== $runQB || $qA !== $runQA) {
                    $ctx->fillRect($originX + $runStart, $originY + $row, $col - $runStart, 1.0, Color::rgba($runR, $runG, $runB, $runA));
                    $runStart = $col;
                    $runR = $r;
                    $runG = $g;
                    $runB = $b;
                    $runA = $a;
                    $runQR = $qR;
                    $runQG = $qG;
                    $runQB = $qB;
                    $runQA = $qA;
                }
            }

            if ($runStart >= 0) {
                $ctx->fillRect($originX + $runStart, $originY + $row, $idstW - $runStart, 1.0, Color::rgba($runR, $runG, $runB, $runA));
            }
        }
    }
}
