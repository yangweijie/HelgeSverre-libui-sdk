<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawImage;
use Yangweijie\Ui2\Rendering\RenderCommand;
use Yangweijie\Ui2\Rendering\RenderCommandList;

/**
 * Draws a bitmap with scaling, fit, and corner-radius.
 *
 * shapeCommands() emits a single {@see DrawImage} — pure geometry, headless-safe.
 * render() delegates to shapeCommands() since no FFI is needed.
 */
final class ImageRenderer implements WidgetRenderer
{
    public static function type(): string
    {
        return 'image';
    }

    /**
     * @return list<RenderCommand>
     */
    public function shapeCommands(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): array
    {
        if (! $spec instanceof ImageSpec) {
            throw new \InvalidArgumentException('ImageRenderer requires an ImageSpec');
        }

        return [
            new DrawImage(
                x: 0.0,
                y: 0.0,
                drawW: $width,
                drawH: $height,
                imgW: $spec->imgW,
                imgH: $spec->imgH,
                pixels: $spec->pixels,
                fit: $spec->fit,
                sampling: $spec->sampling,
                radius: $spec->radius,
            ),
        ];
    }

    public function render(WidgetSpec $spec, DesignTokens $tokens, float $width, float $height): RenderCommandList
    {
        return new RenderCommandList($this->shapeCommands($spec, $tokens, $width, $height));
    }
}
