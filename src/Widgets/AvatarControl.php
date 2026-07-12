<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ImageSpec;

/**
 * A circular-cropped avatar widget (.avatar).
 *
 * Thin wrapper around {@see ImageControl} that sets a corner radius large
 * enough to produce a circle clip (or pill shape for non-square bounds).
 *
 * ```php
 * $av = new AvatarControl('user', $pixels, imgW: 48, imgH: 48, radius: 22);
 * $surface->buildLayout($av->root());
 * ```
 */
final class AvatarControl
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
        float $radius,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'cover',
        string $sampling = 'linear',
    ) {
        $this->leaf = LayoutNode::leaf(
            "avatar:{$name}",
            new ImageSpec(
                imgW: $imgW, imgH: $imgH, pixels: $pixels,
                fit: $fit, sampling: $sampling, radius: $radius,
            ),
            width: $width,
            height: $height,
        );
    }

    /**
     * Create an avatar from a PNG file on disk (requires ext-gd).
     *
     * @param 'stretch'|'contain'|'cover' $fit
     * @param 'nearest'|'linear' $sampling
     * @throws \RuntimeException when GD is unavailable or the file cannot be decoded.
     */
    public static function fromPng(
        string $name,
        string $path,
        float $radius,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'cover',
        string $sampling = 'linear',
    ): self {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('AvatarControl::fromPng() requires the GD extension');
        }

        $im = @\imagecreatefrompng($path);
        if ($im === false) {
            throw new \RuntimeException("Failed to load PNG: {$path}");
        }

        return self::fromGdImage($name, $im, $radius, $width, $height, $fit, $sampling);
    }

    /**
     * Load a file by extension (PNG/JPEG/GIF) and return an AvatarControl.
     *
     * @param 'stretch'|'contain'|'cover' $fit
     * @param 'nearest'|'linear' $sampling
     * @throws \RuntimeException when the format is unsupported or GD fails.
     */
    public static function fromFile(
        string $name,
        string $path,
        float $radius,
        float $width = 48.0,
        float $height = 48.0,
        string $fit = 'cover',
        string $sampling = 'linear',
    ): self {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('AvatarControl::fromFile() requires the GD extension');
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

        return self::fromGdImage($name, $im, $radius, $width, $height, $fit, $sampling);
    }

    /**
     * Build an AvatarControl from a GD image resource.
     *
     * @param resource $im GD image resource.
     * @param 'stretch'|'contain'|'cover' $fit
     * @param 'nearest'|'linear' $sampling
     */
    private static function fromGdImage(
        string $name,
        $im,
        float $radius,
        float $width,
        float $height,
        string $fit,
        string $sampling,
    ): self {
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
            radius: $radius,
            width: $width, height: $height,
            fit: $fit, sampling: $sampling,
        );
    }

    public function root(): LayoutNode
    {
        return $this->leaf;
    }
}
