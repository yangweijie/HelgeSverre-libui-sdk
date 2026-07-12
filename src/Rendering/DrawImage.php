<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering;

/**
 * Draws a bitmap with scaling, fit modes, sampling control, and rounded corners.
 *
 * Replaces the simpler {@see DrawPixels} with full object-fit semantics:
 *   - **stretch** — ignore aspect ratio, fill exactly drawW×drawH
 *   - **contain** — scale to fit inside drawW×drawH, centred, no cropping
 *   - **cover**   — scale to cover drawW×drawH, centred, crop overflow
 *
 * Sampling controls the interpolation when the source is scaled:
 *   - **nearest** — point sampling; crisp pixel-art look
 *   - **linear**  — bilinear interpolation; smooth photographic look
 *
 * When {@see $radius} > 0 the image is clipped to a rounded rectangle (pill shape
 * when the radius equals half the smaller side). An avatar (circle crop) is simply
 * an ImageSpec where `radius >= min(drawW, drawH) / 2`.
 *
 * The command holds raw pixel data and is pure geometry — no FFI — so it can be
 * returned from `shapeCommands()` and verified in headless tests.
 *
 * @readonly
 */
final class DrawImage extends RenderCommand
{
    public const string FIT_STRETCH = 'stretch';
    public const string FIT_CONTAIN = 'contain';
    public const string FIT_COVER = 'cover';

    public const string SAMPLE_NEAREST = 'nearest';
    public const string SAMPLE_LINEAR = 'linear';

    /** @var list<string> */
    public const array VALID_FITS = [self::FIT_STRETCH, self::FIT_CONTAIN, self::FIT_COVER];

    /** @var list<string> */
    public const array VALID_SAMPLES = [self::SAMPLE_NEAREST, self::SAMPLE_LINEAR];

    /**
     * @param float[] $pixels Flat RGBA row-major array, 4 floats per pixel ($r, $g, $b, $a in 0–1).
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $drawW,
        public float $drawH,
        public int $imgW,
        public int $imgH,
        public array $pixels,
        public string $fit = self::FIT_STRETCH,
        public string $sampling = self::SAMPLE_LINEAR,
        public float $radius = 0.0,
    ) {
    }
}
