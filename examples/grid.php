<?php

/**
 * Grid layout demo — positions buttons in a 3×3 grid with varying alignments.
 *
 * Run: php85 examples/grid.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Button;
use Libui\Grid;
use Libui\Generated\Enum\Align;
use Libui\Window;

$grid = new Grid();
$grid->setPadded(true);

// Row 0: three buttons, each with different horizontal alignment
$grid->appendAt(new Button('Fill'),     0, 0, hexpand: true, halign: Align::Fill);
$grid->appendAt(new Button('Center'),   1, 0, hexpand: true, halign: Align::Center);
$grid->appendAt(new Button('End'),      2, 0, hexpand: true, halign: Align::End);

// Row 1: two buttons — Start-aligned + spanning 2 columns
$grid->appendAt(new Button('Start (span 2)'), 0, 1, xspan: 2, hexpand: true, halign: Align::Start);
$grid->appendAt(new Button('Fill'),           2, 1, hexpand: true, halign: Align::Fill);

// Row 2: one button fills the full row (span 3)
$grid->appendAt(new Button('Span entire row'), 0, 2, xspan: 3, hexpand: true, halign: Align::Fill);

$window = new Window('Grid Demo', 480, 320, false);
$window->setChild($grid);
$window->setMargined(true);

App::new()
    ->window($window)
    ->onShouldQuit(fn() => true)
    ->run();
