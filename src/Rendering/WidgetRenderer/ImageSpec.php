<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Spec for a bitmap widget with fit, sampling, and corner-radius control.
 *
 * When {@see $radius} ≥ min(drawable width, drawable height) / 2 the image is
 * clipped to a full circle — this is the avatar mode.
 *
 * @readonly
 */
final class ImageSpec extends WidgetSpec
{
    /**
     * @param float[] $pixels Flat RGBA row-major array, 4 floats per pixel.
     * @param 'stretch'|'contain'|'cover' $fit
     * @param 'nearest'|'linear' $sampling
     */
    public function __construct(
        public int $imgW,
        public int $imgH,
        public array $pixels,
        public string $fit = 'stretch',
        public string $sampling = 'linear',
        public float $radius = 0.0,
    ) {
    }

    public function type(): string
    {
        return 'image';
    }
}
