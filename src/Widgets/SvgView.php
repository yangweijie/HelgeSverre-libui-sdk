<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Area;
use Libui\Control;
use Yangweijie\Ui2\Composite;

/**
 * SVG display widget — renders SVG path data using libui's Area + DrawContext.
 *
 * Supports: paths with fill/stroke, solid colors, opacity.
 * Limitations: no gradients (url(#...)), no CSS inheritance, no dash arrays.
 *
 * ```php
 * $svg = new SvgView(400, 300);
 * $svg->loadFile('icon.svg');
 * // or
 * $svg->loadString('<svg>...</svg>');
 * ```
 */
class SvgView extends Composite
{
    private readonly Area $area;
    private readonly SvgDelegate $delegate;

    public function __construct(int $width = 200, int $height = 200)
    {
        $this->delegate = new SvgDelegate();
        $this->delegate->width = $width;
        $this->delegate->height = $height;
        $this->area = Area::scrolling($this->delegate, $width, $height);
        $this->area->setSize($width, $height);

        // Force redraw after layout — scrolling Area needs time to initialize
        $area = $this->area;
        \Libui\Ffi::timer(100, function () use ($area): bool {
            $area->setSize(300, 300);
            $area->queueRedrawAll();
            return false;
        });
    }

    public function root(): Control
    {
        return $this->area;
    }

    /**
     * Load SVG from a file path.
     */
    public function loadFile(string $path): static
    {
        if (!\file_exists($path)) {
            throw new \RuntimeException("SVG file not found: {$path}");
        }
        $this->loadString(\file_get_contents($path));
        return $this;
    }

    /**
     * Load SVG from a string.
     */
    public function loadString(string $svgContent): static
    {
        $this->delegate->parse($svgContent);
        $this->area->queueRedrawAll();
        return $this;
    }

    /**
     * Load raw path data (one or more `d` attribute strings).
     */
    public function loadPaths(array $paths): static
    {
        $this->delegate->setPaths($paths);
        $this->area->queueRedrawAll();
        return $this;
    }

    // ── Mouse event registration (forwards to SvgDelegate) ──

    /**
     * Register a handler for when an SVG element is clicked (button down).
     *
     * The handler receives: `['index' => int, 'element' => array, 'x' => float, 'y' => float, 'type' => string]`
     */
    public function onClick(callable $handler): static
    {
        $this->delegate->on('click', $handler);
        return $this;
    }

    /**
     * Register a handler for when an SVG element is double-clicked.
     *
     * Payload same as onClick.
     */
    public function onDoubleClick(callable $handler): static
    {
        $this->delegate->on('dblclick', $handler);
        return $this;
    }

    /**
     * Register a handler for mouse movement over the SVG area.
     *
     * The handler receives: `['x' => float, 'y' => float, 'index' => ?int, 'element' => ?array, 'type' => ?string]`
     */
    public function onMouseMove(callable $handler): static
    {
        $this->delegate->on('mousemove', $handler);
        return $this;
    }

    /**
     * Register a handler for when the hovered SVG element changes.
     *
     * The handler receives: `['index' => ?int, 'element' => ?array, 'type' => ?string]`
     * `index` is null when the mouse leaves all elements.
     */
    public function onHoverChange(callable $handler): static
    {
        $this->delegate->on('hoverchange', $handler);
        return $this;
    }

    /**
     * Register a handler for when the mouse enters the SVG area.
     */
    public function onMouseEnter(callable $handler): static
    {
        $this->delegate->on('mouseenter', $handler);
        return $this;
    }

    /**
     * Register a handler for when the mouse leaves the SVG area.
     */
    public function onMouseLeave(callable $handler): static
    {
        $this->delegate->on('mouseleave', $handler);
        return $this;
    }

    /**
     * Register a handler for right-click (context menu) on an SVG element.
     *
     * Payload same as onClick.
     */
    public function onContextMenu(callable $handler): static
    {
        $this->delegate->on('contextmenu', $handler);
        return $this;
    }
}
