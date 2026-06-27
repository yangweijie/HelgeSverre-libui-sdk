<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\Build;
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

$window = new Window('Custom Widgets Demo', 500, 400, true);

$statusLabel = new Label('Interact with the custom widgets below →');

// --- ToggleSwitch ---
$toggle = new ToggleSwitch('Enable feature', true);
$toggle->onChange(function (bool $on) use ($statusLabel): void {
    $statusLabel->setText('Toggle: ' . ($on ? 'ON' : 'OFF'));
});

// --- StatusIndicator ---
$indicator = new StatusIndicator('green', 'System running');

// --- CircleProgressBar ---
$progress = new CircleProgressBar(0, 100);
$progress->setValue(65);

// --- Toast area ---
$showToastBtn = new \Libui\Button('Show Toast');
$showToastBtn->onClick(function () use ($window): void {
    Toast::show($window, 'Action completed!', 3000);
});

$btnToggleState = new \Libui\Button('Toggle Indicator');
$btnToggleState->onClick(function () use ($indicator, $statusLabel): void {
    $states = ['green', 'yellow', 'red'];
    $current = $indicator->getState();
    $next = $states[(\array_search($current, $states, true) + 1) % 3] ?: 'green';
    $indicator->setState($next);
    $indicator->setLabel('Status: ' . $next);
    $statusLabel->setText('Indicator changed to: ' . $next);
});

$btnProgress = new \Libui\Button('+10%');
$btnProgress->onClick(function () use ($progress, $statusLabel): void {
    $val = $progress->getValue() + 10;
    if ($val > 100) $val = 0;
    $progress->setValue($val);
    $statusLabel->setText('Progress: ' . $val . '%');
});

$window->setChild(Build::vbox(
    $statusLabel,
    $toggle,
    $indicator,
    Build::hbox(new Label('Progress:'), $progress),
    Build::hbox($showToastBtn, $btnToggleState, $btnProgress),
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();