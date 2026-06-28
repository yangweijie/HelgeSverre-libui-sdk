<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Widgets\SvgView;

/**
 * SVG display demo — renders SVG using SvgView component.
 *
 * Run: php85 examples/test-svg.php
 */

Ffi::init();

$window = new Window('SVG View Demo', 600, 500, true);
$outputLabel = new Label('SVG rendered in libui Area');

// ── SVG with paths, text, circles, lines ──
$sampleSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" height="400" width="450">
  <path id="lineAB" d="M 100 350 l 150 -300" stroke="red" stroke-width="3" fill="none" />
  <path id="lineBC" d="M 250 50 l 150 300" stroke="red" stroke-width="3" fill="none" />
  <path d="M 175 200 l 150 0" stroke="green" stroke-width="3" fill="none" />
  <path d="M 100 350 q 150 -300 300 0" stroke="blue" stroke-width="5" fill="none" />
  <g stroke="black" stroke-width="3" fill="black">
    <circle id="pointA" cx="100" cy="350" r="3" />
    <circle id="pointB" cx="250" cy="50" r="3" />
    <circle id="pointC" cx="400" cy="350" r="3" />
  </g>
  <g font-size="30" font="sans-serif" fill="black" stroke="none" text-anchor="middle">
    <text x="100" y="350" dx="-30">A</text>
    <text x="250" y="50" dy="-10">B</text>
    <text x="400" y="350" dx="30">C</text>
  </g>
</svg>
SVG;

$svgView = new SvgView(450, 400);
$svgView->loadString($sampleSvg);

// ── Buttons ──
$btnLoadFile = new \Libui\Button('Load File');
$btnLoadFile->onClicked(function () use ($svgView, $outputLabel): void {
    $svg2 = <<<'SVG'
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="10" width="180" height="180" fill="#8B5CF6" stroke="#6D28D9" stroke-width="3"/>
  <circle cx="100" cy="100" r="50" fill="#EF4444"/>
  <circle cx="100" cy="100" r="30" fill="#FBBF24"/>
  <circle cx="100" cy="100" r="10" fill="#10B981"/>
</svg>
SVG;
    $svgView->loadString($svg2);
    $outputLabel->setText('Loaded: concentric circles');
});

$btnReload = new \Libui\Button('Reset SVG');
$btnReload->onClicked(function () use ($svgView, $outputLabel, $sampleSvg): void {
    $svgView->loadString($sampleSvg);
    $outputLabel->setText('Reset to geometric diagram');
});

$window->setChild(Build::vbox(
    Build::hbox($btnLoadFile, $btnReload, Build::stretchy(new Label(''))),
    Build::stretchy($svgView->root()),
    $outputLabel,
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
