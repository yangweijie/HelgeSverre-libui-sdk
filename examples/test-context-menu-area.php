<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\Build;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Draw\Params\AreaMouseEvent;
use Libui\Draw\StrokeParams;
use Libui\Draw\Brush;
use Libui\Draw\Path;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\AreaDelegate;
use Yangweijie\Ui2\Widgets\ContextMenu;

/**
 * ContextMenu + Area demo — right-click inside the Area to show a native popup.
 *
 * Run: php85 examples/test-context-menu-area.php
 */

Ffi::init();

// ── Shared state ──
$statusLabel = new Label('Right-click inside the blue rectangle →');

// ── Build the context menu ──
$menu = new ContextMenu();
$menu->addItem('Red', function (ContextMenu $m) use ($statusLabel): void {
    $area = $m->getSource();
    $statusLabel->setText('Selected: Red (area at x=30)');
})
->addItem('Green', function (ContextMenu $m) use ($statusLabel): void {
    $statusLabel->setText('Selected: Green');
})
->addItem('Blue', function (ContextMenu $m) use ($statusLabel): void {
    $statusLabel->setText('Selected: Blue');
})
->addSeparator()
->addItem('Properties…', function (ContextMenu $m) use ($statusLabel): void {
    $src = $m->getSource();
    $statusLabel->setText('Properties of source: ' . get_class($src));
}, checked: true)
->addSeparator()
->addItem('Disabled Item', disabled: true);

// ── Area delegate — handles right-click to show context menu ──
$delegate = new class($menu, $statusLabel) extends AreaDelegate {
    public function __construct(
        private ContextMenu $menu,
        private Label $status,
    ) {}

    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $ctx->fillRect(20, 20, 200, 150, Brush::solid(0.3, 0.5, 0.9, 1.0));
        $ctx->strokeRect(20, 20, 200, 150, Brush::solid(1, 1, 1, 1),
            (new StrokeParams())->thickness(2));
    }

    public function mouse(AreaMouseEvent $event): void
    {
        // Right-click = button 2 or 3 depending on platform
        $isRightClick = ($event->down === 2 || $event->down === 3);

        if ($isRightClick) {
            $this->menu->setSource($this)->show();
        }
    }
};

$area = new Area($delegate);

$window = new Window('ContextMenu + Area Demo', 500, 350, true);
$window->setChild(Build::vbox(
    $statusLabel,
    Build::stretchy($area),
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
