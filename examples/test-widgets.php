<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\Build;
use Libui\Color;
use Libui\Label;
use Libui\Window;
use Libui\Draw\Brush;
use Libui\Draw\Path;
use Libui\Draw\DrawContext;
use Libui\Draw\StrokeParams;
use Libui\Draw\Params\AreaDrawParams;
use Libui\AreaDelegate;
use Yangweijie\Ui2\Widgets\CircleProgressBar;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\Toast;
use Yangweijie\Ui2\Widgets\ToggleSwitch;

/**
 * Custom widgets demo — ToggleSwitch, StatusIndicator, CircleProgressBar, Toast
 *
 * Run: php85 examples/test-widgets.php
 */

$window = new Window('Custom Widgets Demo', 500, 550, true);

$statusLabel = new Label('Interact with the custom widgets below →');

// --- ToggleSwitch ---
$toggle = new ToggleSwitch(true);
$toggle->on('change', function (bool $on) use ($statusLabel): void {
    $statusLabel->setText('Toggle: ' . ($on ? 'ON' : 'OFF'));
});

// --- StatusIndicator ---
$indicator = new StatusIndicator(Color::rgb(0x22c55e));

// --- CircleProgressBar ---
$progress = new CircleProgressBar(65, 120);

// --- Toast area ---
$showToastBtn = new \Libui\Button('Show Toast');
$showToastBtn->onClicked(function () use ($window): void {
    Toast::show('Action', 'Action completed!');
});

$btnToggleState = new \Libui\Button('Toggle Indicator');
$btnToggleState->onClicked(function () use ($indicator, $statusLabel): void {
    $colors = [
        Color::rgb(0x22c55e),
        Color::rgb(0xeab308),
        Color::rgb(0xef4444),
    ];
    static $idx = 0;
    $idx = ($idx + 1) % 3;
    $indicator->setColor($colors[$idx]);
    $statusLabel->setText('Indicator changed');
});

$btnProgress = new \Libui\Button('+10%');
$btnProgress->onClicked(function () use ($progress, $statusLabel): void {
    $val = $progress->getProgress() + 10;
    if ($val > 100) $val = 0;
    $progress->setProgress($val);
    $statusLabel->setText('Progress: ' . $val . '%');
});

$window->setChild(Build::vbox(
    $statusLabel,
    $toggle->root(),
    $indicator->root(),
    Build::hbox(new Label('Progress:'), $progress->root()),
    Build::hbox($showToastBtn, $btnToggleState, $btnProgress),
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();