<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;
use Yangweijie\Ui2\Dialogs\MessageBox;

/**
 * Dialog demo — MessageBox, Confirm, Prompt
 *
 * Run: php85 examples/test-dialogs.php
 */

$window = new Window('Dialog Demo', 450, 300, true);

$statusLabel = new Label('Click a button to show a dialog →');

$btnInfo = new Button('Info');
$btnInfo->onClick(function () use ($window, $statusLabel): void {
    MessageBox::info($window, 'Information', 'This is an informational message.');
    $statusLabel->setText('Info dialog closed');
});

$btnWarning = new Button('Warning');
$btnWarning->onClick(function () use ($window, $statusLabel): void {
    MessageBox::warning($window, 'Warning', 'This is a warning message.');
    $statusLabel->setText('Warning dialog closed');
});

$btnError = new Button('Error');
$btnError->onClick(function () use ($window, $statusLabel): void {
    MessageBox::error($window, 'Error', 'This is an error message.');
    $statusLabel->setText('Error dialog closed');
});

$btnConfirm = new Button('Confirm');
$btnConfirm->onClick(function () use ($window, $statusLabel): void {
    $result = DialogConfirm::ask($window, 'Confirm', 'Are you sure you want to proceed?');
    $statusLabel->setText('Confirm result: ' . ($result ? 'Yes' : 'No'));
});

$btnPrompt = new Button('Prompt');
$btnPrompt->onClick(function () use ($window, $statusLabel): void {
    $result = DialogPrompt::ask($window, 'Input', 'Enter your name:', 'default name');
    $statusLabel->setText('Prompt result: ' . ($result ?? 'cancelled'));
});

$window->setChild(Build::vbox(
    $statusLabel,
    Build::hbox($btnInfo, $btnWarning, $btnError),
    $btnConfirm,
    $btnPrompt,
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();