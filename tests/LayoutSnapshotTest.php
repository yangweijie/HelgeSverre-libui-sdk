<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers/LayoutSnapshot.php';

use Yangweijie\Ui2\Compiler\NativeLoader;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;

/**
 * Layout snapshot tests — golden-file assertions for UI structure + rendering commands.
 *
 * First run creates baselines in tests/__snapshots__/layout-*.snap.
 * Subsequent runs compare. Delete .snap files to update baselines.
 *
 * These tests are fully headless — no FFI or display needed.
 */

test('single button layout snapshot', function (): void {
    $root = LayoutNode::leaf('btn', new ButtonSpec('Save', 'filled'), width: 120.0, height: 36.0);

    LayoutSnapshot::assert('single-button', $root, 200.0, 50.0);
});

test('button row layout snapshot', function (): void {
    $root = LayoutNode::row(gap: 8.0, align: 'center', height: 44.0)
        ->child(LayoutNode::leaf('save', new ButtonSpec('Save', 'filled'), width: 100.0, height: 36.0))
        ->child(LayoutNode::leaf('cancel', new ButtonSpec('Cancel', 'outline'), width: 100.0, height: 36.0));

    LayoutSnapshot::assert('button-row', $root, 400.0, 50.0);
});

test('button variants snapshot', function (): void {
    $root = LayoutNode::row(gap: 8.0, align: 'center', height: 44.0)
        ->child(LayoutNode::leaf('filled', new ButtonSpec('Filled', 'filled'), width: 80.0, height: 36.0))
        ->child(LayoutNode::leaf('soft', new ButtonSpec('Soft', 'soft'), width: 80.0, height: 36.0))
        ->child(LayoutNode::leaf('outline', new ButtonSpec('Outline', 'outline'), width: 80.0, height: 36.0))
        ->child(LayoutNode::leaf('disabled', new ButtonSpec('Disabled', 'filled', false), width: 80.0, height: 36.0));

    LayoutSnapshot::assert('button-variants', $root, 400.0, 50.0);
});

test('card with label and button snapshot', function (): void {
    $root = LayoutNode::column(gap: 8.0, padding: 12.0, width: 300.0, height: 120.0);
    $root->spec = new CardSpec(bordered: true, radius: 8.0);
    $root->child(LayoutNode::leaf('title', new LabelSpec('Card Title', size: 16.0), height: 24.0))
        ->child(LayoutNode::leaf('desc', new LabelSpec('Description text', size: 12.0, opacity: 0.6), height: 18.0))
        ->child(LayoutNode::row(gap: 8.0, height: 36.0)
            ->child(LayoutNode::leaf('ok', new ButtonSpec('OK', 'filled'), width: 80.0, height: 36.0))
            ->child(LayoutNode::leaf('cancel', new ButtonSpec('Cancel', 'outline'), width: 80.0, height: 36.0)));

    LayoutSnapshot::assert('card-with-actions', $root, 350.0, 140.0);
});

test('form fields snapshot', function (): void {
    $root = LayoutNode::column(gap: 8.0, padding: 12.0, width: 300.0, height: 160.0)
        ->child(LayoutNode::leaf('name', new TextFieldSpec('', 'Enter name…'), width: 280.0, height: 36.0))
        ->child(LayoutNode::leaf('email', new TextFieldSpec('', 'user@example.com'), width: 280.0, height: 36.0))
        ->child(LayoutNode::leaf('agree', new CheckboxSpec(checked: false, enabled: true, label: 'I agree to terms'), width: 280.0, height: 28.0))
        ->child(LayoutNode::leaf('submit', new ButtonSpec('Submit', 'filled'), width: 100.0, height: 36.0));

    LayoutSnapshot::assert('form-fields', $root, 350.0, 200.0);
});

test('slider and progress snapshot', function (): void {
    $root = LayoutNode::column(gap: 12.0, padding: 16.0, width: 300.0, height: 120.0)
        ->child(LayoutNode::leaf('slider', new SliderSpec(0.6), width: 280.0, height: 20.0))
        ->child(LayoutNode::leaf('progress', new ProgressSpec(0.45), width: 280.0, height: 12.0))
        ->child(LayoutNode::leaf('label', new LabelSpec('45%', size: 12.0, align: 'center'), width: 280.0, height: 18.0));

    LayoutSnapshot::assert('slider-progress', $root, 350.0, 160.0);
});

test('nested containers snapshot', function (): void {
    $root = LayoutNode::column(gap: 8.0, padding: 12.0, width: 400.0, height: 200.0)
        ->child(LayoutNode::row(gap: 8.0, height: 36.0)
            ->child(LayoutNode::leaf('a', new ButtonSpec('A', 'filled'), width: 80.0, height: 36.0))
            ->child(LayoutNode::leaf('b', new ButtonSpec('B', 'soft'), width: 80.0, height: 36.0)))
        ->child(LayoutNode::row(gap: 8.0, height: 36.0)
            ->child(LayoutNode::leaf('c', new ButtonSpec('C', 'outline'), width: 80.0, height: 36.0))
            ->child(LayoutNode::leaf('d', new ButtonSpec('D', 'filled'), width: 80.0, height: 36.0)));

    LayoutSnapshot::assert('nested-rows', $root, 450.0, 250.0);
});

test('NativeLoader counter.native snapshot', function (): void {
    $path = __DIR__ . '/../examples/counter.native';
    if (!file_exists($path)) {
        markTestSkipped('counter.native not found');
    }

    $root = NativeLoader::load($path);

    LayoutSnapshot::assert('dsl-counter', $root, 400.0, 200.0);
});

test('grow distribution snapshot', function (): void {
    $root = LayoutNode::row(gap: 0.0, height: 40.0)
        ->child(LayoutNode::leaf('fixed', new ButtonSpec('Fixed', 'filled'), width: 100.0, height: 36.0));
    $growing = LayoutNode::leaf('growing', new ButtonSpec('Growing', 'soft'), height: 36.0);
    $growing->style->grow = 1.0;
    $root->child($growing);

    LayoutSnapshot::assert('grow-distribution', $root, 400.0, 50.0);
});
