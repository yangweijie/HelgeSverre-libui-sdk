<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ImageSpec;

/**
 * A bitmap widget (.image) with scaling, fit, sampling, and corner-radius.
 *
 * Accepts raw RGBA pixel data or loads from a file via the static factories:
 *
 * ```php
 * // Raw pixel data (square 48×48 gradient sphere)
 * $img = new ImageControl('avatar', $pixels, imgW: 48, imgH: 48);
 *
 * // Load PNG from disk (requires ext-gd)
 * $img = ImageControl::fromPng('photo', '/path/to/photo.png');
 * ```
 *
 * When {@see $radius} ≥ min(width, height) / 2 the image is clipped to a
 * full circle — this is the avatar variant.
 */
final class ImageControl
{
    private LayoutNode $leaf;

    /**
     * @param float[] $pixels Flat RGBA row-major array, 4 floats per pixel.
     * @param 'stretch'|'contain'|'cover' $fit
     * @param 'nearest'|'linear' $sampling
     */
    public function __construct(
        private readonly string $name,
        array $pixels,
        int $imgW,
        int $imgH,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'stretch',
        string $sampling = 'linear',
        float $radius = 0.0,
    ) {
        $this->leaf = LayoutNode::leaf(
            "image:{$name}",
            new ImageSpec(
                imgW: $imgW, imgH: $imgH, pixels: $pixels,
                fit: $fit, sampling: $sampling, radius: $radius,
            ),
            width: $width,
            height: $height,
        );
    }

    /**
     * Load a PNG file and return an ImageControl.
     *
     * Requires the GD extension. Throws a descriptive exception when GD is
     * unavailable or the file cannot be decoded.
     */
    public static function fromPng(
        string $name,
        string $path,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'stretch',
        string $sampling = 'linear',
        float $radius = 0.0,
    ): self {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('ImageControl::fromPng() requires the GD extension');
        }

        $im = @\imagecreatefrompng($path);
        if ($im === false) {
            throw new \RuntimeException("Failed to load PNG: {$path}");
        }

        $w = \imagesx($im);
        $h = \imagesy($im);
        $pixels = [];

        // Prevent GD from blending alpha with a black background during pixel read
        \imagealphablending($im, false);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = \imagecolorat($im, $x, $y);
                // GD imagecolorat() returns 0xAARRGGBB: bits 16-23=R, 8-15=G, 0-7=B, 24-31=A
                $pixels[] = (($rgba >> 16) & 0xFF) / 255.0;  // R
                $pixels[] = (($rgba >> 8) & 0xFF) / 255.0;   // G
                $pixels[] = ($rgba & 0xFF) / 255.0;           // B
                // GD stores alpha inverted: 0 = opaque, 127 = transparent.
                // Our renderer expects 0.0 = transparent, 1.0 = opaque → invert.
                $pixels[] = 1.0 - (($rgba >> 24) & 0x7F) / 127.0;
            }
        }

        \imagedestroy($im);

        return new self(
            $name, $pixels, $w, $h,
            width: $width, height: $height,
            fit: $fit, sampling: $sampling, radius: $radius,
        );
    }

    /**
     * Load a file by extension (PNG/JPEG/GIF).
     *
     * @throws \RuntimeException when the format is unsupported or GD fails.
     */
    public static function fromFile(
        string $name,
        string $path,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'stretch',
        string $sampling = 'linear',
        float $radius = 0.0,
    ): self {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('ImageControl::fromFile() requires the GD extension');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $im = match ($ext) {
            'png' => @\imagecreatefrompng($path),
            'jpg', 'jpeg' => @\imagecreatefromjpeg($path),
            'gif' => @\imagecreatefromgif($path),
            default => throw new \RuntimeException("Unsupported image format: .{$ext}"),
        };

        if ($im === false) {
            throw new \RuntimeException("Failed to load image: {$path}");
        }

        $w = \imagesx($im);
        $h = \imagesy($im);
        $pixels = [];

        // Prevent GD from blending alpha with a black background during pixel read
        \imagealphablending($im, false);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = \imagecolorat($im, $x, $y);
                // GD imagecolorat() returns 0xAARRGGBB: bits 16-23=R, 8-15=G, 0-7=B, 24-31=A
                $pixels[] = (($rgba >> 16) & 0xFF) / 255.0;  // R
                $pixels[] = (($rgba >> 8) & 0xFF) / 255.0;   // G
                $pixels[] = ($rgba & 0xFF) / 255.0;           // B
                // GD stores alpha inverted: 0 = opaque, 127 = transparent.
                // Our renderer expects 0.0 = transparent, 1.0 = opaque → invert.
                $pixels[] = 1.0 - (($rgba >> 24) & 0x7F) / 127.0;
            }
        }

        \imagedestroy($im);

        return new self(
            $name, $pixels, $w, $h,
            width: $width, height: $height,
            fit: $fit, sampling: $sampling, radius: $radius,
        );
    }

    public function root(): LayoutNode
    {
        return $this->leaf;
    }
}
