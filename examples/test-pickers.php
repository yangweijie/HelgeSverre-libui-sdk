<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Pickers\ColorPickerDialog;
use Yangweijie\Ui2\Pickers\DatePickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Pickers\TimePickerDialog;

/**
 * Picker dialogs demo — Color, Font, Date, Time pickers
 *
 * Run: php85 examples/test-pickers.php
 */

$window = new Window('Picker Demo', 500, 300, true);

$statusLabel = new Label('Click a button to open a picker dialog →');

// Color picker
$btnColor = new \Libui\Button('Pick Color');
$btnColor->onClick(function () use ($window, $statusLabel): void {
    $color = ColorPickerDialog::pick($window);
    if ($color !== null) {
        $statusLabel->setText(\sprintf(
            'Color: R=%d G=%d B=%d A=%.2f',
            (int)($color->r * 255), (int)($color->g * 255),
            (int)($color->b * 255), $color->a,
        ));
    } else {
        $statusLabel->setText('Color picker cancelled');
    }
});

// Font picker
$btnFont = new \Libui\Button('Pick Font');
$btnFont->onClick(function () use ($window, $statusLabel): void {
    $font = FontPickerDialog::pick($window);
    if ($font !== null) {
        $statusLabel->setText(\sprintf(
            'Font: %s, size=%.1f, weight=%d, italic=%s',
            $font->family, $font->size, $font->weight,
            $font->italic ? 'yes' : 'no',
        ));
    } else {
        $statusLabel->setText('Font picker cancelled');
    }
});

// Date picker
$btnDate = new \Libui\Button('Pick Date');
$btnDate->onClick(function () use ($window, $statusLabel): void {
    $date = DatePickerDialog::pick($window);
    if ($date !== null) {
        $statusLabel->setText('Date: ' . $date->format('Y-m-d'));
    } else {
        $statusLabel->setText('Date picker cancelled');
    }
});

// Time picker
$btnTime = new \Libui\Button('Pick Time');
$btnTime->onClick(function () use ($window, $statusLabel): void {
    $time = TimePickerDialog::pick($window);
    if ($time !== null) {
        $statusLabel->setText('Time: ' . $time->format('H:i:s'));
    } else {
        $statusLabel->setText('Time picker cancelled');
    }
});

$window->setChild(Build::vbox(
    $statusLabel,
    Build::hbox($btnColor, $btnFont),
    Build::hbox($btnDate, $btnTime),
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();