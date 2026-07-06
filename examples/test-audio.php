<?php

/*
 * Audio playback demo — exercises the Audio system (bridge/audio.{dylib,so,dll}).
 *
 * Run:  php85 examples/test-audio.php
 *
 * Uses the libui file dialog to pick an audio file, then demonstrates
 * load / play / pause / resume / stop / volume / loop / onEnded.
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\Build;
use Libui\Button;
use Libui\Checkbox;
use Libui\Dialogs;
use Libui\Label;
use Libui\Loop;
use Libui\Slider;
use Libui\Window;
use Yangweijie\Ui2\System\Audio;

$window = new Window('Audio Demo', 480, 340, false);
$window->setMargined(true);

$pathLabel   = new Label('—');
$statusLabel = new Label('Open an audio file to begin');

$btnOpen   = new Button('Open…');
$btnPlay   = new Button('Play');
$btnPause  = new Button('Pause');
$btnResume = new Button('Resume');
$btnStop   = new Button('Stop');
$loopChk   = new Checkbox('Loop');
$volSlider = new Slider(0, 100);
$volSlider->setValue(80);

/** @var Audio|null $sound */
$sound = null;

$dialogs = Dialogs::for($window);

$btnOpen->onClicked(function () use ($dialogs, &$sound, $pathLabel, $statusLabel, $volSlider, $loopChk): void {
    $file = $dialogs->openFile();
    if ($file === null) {
        return;
    }
    if ($sound !== null) {
        $sound->unload();
        $sound = null;
    }
    try {
        $sound = Audio::load($file)
            ->setVolume($volSlider->value() / 100.0)
            ->setLooping($loopChk->value());
        $pathLabel->setText(\basename($file));
        $statusLabel->setText('Loaded — press Play');
    } catch (\Throwable $e) {
        $statusLabel->setText('Error: ' . $e->getMessage());
    }
});

$btnPlay->onClicked(function () use (&$sound, $statusLabel, $volSlider, $loopChk): void {
    if ($sound === null) {
        $statusLabel->setText('Open a file first');
        return;
    }
    $sound
        ->setVolume($volSlider->value() / 100.0)
        ->setLooping($loopChk->value())
        ->onEnded(function () use ($statusLabel): void {
            $statusLabel->setText('Finished');
        })
        ->play();
    $statusLabel->setText('Playing…');
});

$btnPause->onClicked(function () use (&$sound, $statusLabel): void {
    if ($sound !== null) {
        $sound->pause();
        $statusLabel->setText('Paused');
    }
});

$btnResume->onClicked(function () use (&$sound, $statusLabel): void {
    if ($sound !== null) {
        $sound->resume();
        $statusLabel->setText('Playing…');
    }
});

$btnStop->onClicked(function () use (&$sound, $statusLabel): void {
    if ($sound !== null) {
        $sound->stop();
        $statusLabel->setText('Stopped');
    }
});

$volSlider->onChanged(function (Slider $sender) use (&$sound): void {
    if ($sound !== null) {
        $sound->setVolume($sender->value() / 100.0);
    }
});

$loopChk->onToggled(function () use (&$sound, $loopChk): void {
    if ($sound !== null) {
        $sound->setLooping($loopChk->value());
    }
});

$window->onClosing(function () use (&$sound): bool {
    if ($sound !== null) {
        $sound->unload();
    }
    Audio::shutdown();
    return true;
});

$window->setChild(Build::vbox(
    Build::hbox($btnOpen, Build::stretchy(new Label(''))),
    $pathLabel,
    Build::hbox($btnPlay, $btnPause, $btnResume, $btnStop),
    Build::hbox(new Label('Volume'), $volSlider),
    $loopChk,
    $statusLabel,
));

Audio::init();
$window->show();
Loop::run();
