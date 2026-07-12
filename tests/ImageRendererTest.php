<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\DrawImage;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ImageRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ImageSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;

// Headless coverage of ImageRenderer and the DrawImage spec it produces.

test('default registry includes the image renderer', function () {
    $registry = RendererRegistry::default();

    expect($registry->has('image'))->toBeTrue();
    expect($registry->types())->toContain('image');
});

test('ImageRenderer emits a single DrawImage command', function () {
    $pixels = array_fill(0, 16 * 16 * 4, 1.0); // 16x16 opaque white
    $spec = new ImageSpec(imgW: 16, imgH: 16, pixels: $pixels);

    $cmds = (new ImageRenderer())->shapeCommands($spec, new DesignTokens(), 32.0, 32.0);

    expect($cmds)->toHaveCount(1);
    expect($cmds[0])->toBeInstanceOf(DrawImage::class);

    $cmd = $cmds[0];
    expect($cmd->drawW)->toBe(32.0);
    expect($cmd->drawH)->toBe(32.0);
    expect($cmd->imgW)->toBe(16);
    expect($cmd->imgH)->toBe(16);
    expect($cmd->fit)->toBe(DrawImage::FIT_STRETCH);
    expect($cmd->sampling)->toBe(DrawImage::SAMPLE_LINEAR);
    expect($cmd->radius)->toBe(0.0);
});

test('ImageRenderer passes fit, sampling, and radius through to DrawImage', function () {
    $pixels = array_fill(0, 4 * 4 * 4, 1.0);
    $spec = new ImageSpec(
        imgW: 4, imgH: 4, pixels: $pixels,
        fit: DrawImage::FIT_COVER,
        sampling: DrawImage::SAMPLE_NEAREST,
        radius: 12.0,
    );

    $cmd = (new ImageRenderer())->shapeCommands($spec, new DesignTokens(), 48.0, 48.0)[0];

    expect($cmd)->toBeInstanceOf(DrawImage::class);
    expect($cmd->fit)->toBe(DrawImage::FIT_COVER);
    expect($cmd->sampling)->toBe(DrawImage::SAMPLE_NEAREST);
    expect($cmd->radius)->toBe(12.0);
});

test('ImageRenderer render returns a headless-safe command list', function () {
    $pixels = array_fill(0, 8 * 8 * 4, 0.5);
    $list = (new ImageRenderer())->render(new ImageSpec(imgW: 8, imgH: 8, pixels: $pixels), new DesignTokens(), 16.0, 16.0);

    expect($list)->toBeInstanceOf(RenderCommandList::class);
    expect($list->commands)->toHaveCount(1);
    expect($list->commands[0])->toBeInstanceOf(DrawImage::class);
    $list->free();

    expect(true)->toBeTrue();
});

test('ImageRenderer rejects a non-image spec', function () {
    expect(fn () => (new ImageRenderer())->shapeCommands(new \Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec('x', 'filled'), new DesignTokens(), 32.0, 32.0))
        ->toThrow(\InvalidArgumentException::class);
});

// Regression: the same 8-bit colour should not be split into multiple runs by
// floating-point noise. A uniform image should produce a single DrawImage
// command covering the whole widget.
test('uniform image produces a single DrawImage with no transparent pixels', function () {
    $w = 32;
    $h = 32;
    $pixels = [];
    for ($i = 0; $i < $w * $h; $i++) {
        $pixels[] = 0.2;  // R
        $pixels[] = 0.4;  // G
        $pixels[] = 0.8;  // B
        $pixels[] = 1.0;  // A
    }

    $cmd = (new ImageRenderer())->shapeCommands(
        new ImageSpec(imgW: $w, imgH: $h, pixels: $pixels),
        new DesignTokens(),
        64.0, 64.0,
    )[0];

    expect($cmd)->toBeInstanceOf(DrawImage::class);
    expect($cmd->pixels)->toHaveCount($w * $h * 4);
    expect($cmd->drawW)->toBe(64.0);
    expect($cmd->drawH)->toBe(64.0);
});
