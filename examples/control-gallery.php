<?php

/**
 * Control Gallery — 自绘控件版，演示所有基础 WidgetSpec
 * ──────────────────────────────────────────────────────────────────────
 * 原版 (libui native) 改造为 Surface 自绘：
 *   左栏：Button / Checkbox / Label / DatePicker / FontPicker / ColorPicker
 *   右栏：Number / Slider / Progress / Select / Radio / TabControl
 * FontButton 和 ColorButton 无自绘替代，保留原生 Button 触发 picker。
 *
 * Run: php85 examples/control-gallery.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Pickers\ColorPickerDialog;
use Yangweijie\Ui2\Pickers\DatePickerDialog;
use Yangweijie\Ui2\Pickers\FilePickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DatePickerSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\FilePickerSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\NumberSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Widgets\Surface;
use Yangweijie\Ui2\Widgets\TabControl;

// ═════════════════════════════════════════════════════════════════════════════
// LEFT — Basic Controls
// ═════════════════════════════════════════════════════════════════════════════

$chkLeaf = LayoutNode::leaf('chk', new CheckboxSpec(label: 'Checkbox'), height: 32);
$dateLeaf = LayoutNode::leaf('date', new DatePickerSpec(placeholder: '选择日期…'), height: 34);
$fileLeaf = LayoutNode::leaf('file', new FilePickerSpec(placeholder: '选择文件…'), height: 34);

$leftCol = LayoutNode::column(gap: 8, padding: 0, align: LayoutStyle::ALIGN_STRETCH, width: 240, height: 480)
    ->child(LayoutNode::leaf(null, new LabelSpec('Basic Controls', size: 14.0), height: 22))
    ->child(LayoutNode::leaf('btn', new ButtonSpec('Button'), height: 36))
    ->child($chkLeaf)
    ->child(LayoutNode::leaf('lbl', new LabelSpec('Label — 自绘标签'), height: 28))
    ->child($dateLeaf)
    ->child($fileLeaf)
    ->child(LayoutNode::leaf(null, new LabelSpec('FontButton（原生 picker）', size: 11.0, opacity: 0.6), height: 20))
    ->child(LayoutNode::leaf('fontBtn', new ButtonSpec('Pick Font'), height: 36))
    ->child(LayoutNode::leaf(null, new LabelSpec('ColorButton（原生 picker）', size: 11.0, opacity: 0.6), height: 20))
    ->child(LayoutNode::leaf('colorBtn', new ButtonSpec('Pick Color'), height: 36));

// ═════════════════════════════════════════════════════════════════════════════
// RIGHT — Numbers / Lists / Tab
// ═════════════════════════════════════════════════════════════════════════════

// Numbers
$numLeaf = LayoutNode::leaf('num', new NumberSpec(value: '50', placeholder: '0–100', min: 0, max: 100), height: 34);
$sliderLeaf = LayoutNode::leaf('slider', new SliderSpec(value: 0.5), height: 32);
$progressLeaf = LayoutNode::leaf('progress', new ProgressSpec(value: 0.35), height: 22);

$numbersCol = LayoutNode::column(gap: 8, padding: 0, align: LayoutStyle::ALIGN_STRETCH, width: 280, height: 130)
    ->child(LayoutNode::leaf(null, new LabelSpec('Numbers', size: 14.0), height: 22))
    ->child($numLeaf)
    ->child($sliderLeaf)
    ->child($progressLeaf);

// Lists
$comboLeaf = LayoutNode::leaf('combo', new TextFieldSpec(value: 'Combobox Item 1', placeholder: 'Select…'), height: 34);

$editComboLeaf = LayoutNode::leaf('editCombo', new TextFieldSpec(placeholder: 'Editable Combobox…'), height: 34);

$radioOptions = ['Radio Button 1', 'Radio Button 2', 'Radio Button 3'];
$radioNodes = [];
$radioCol = LayoutNode::column(gap: 4, padding: 0, id: 'radio-group', width: 280, height: 100);
foreach ($radioOptions as $i => $label) {
    $leaf = LayoutNode::leaf("radio:{$i}", new RadioSpec(selected: $i === 0, label: $label), height: 28);
    $radioNodes[] = $leaf;
    $radioCol->child($leaf);
}

$listsCol = LayoutNode::column(gap: 8, padding: 0, align: LayoutStyle::ALIGN_STRETCH, width: 280, height: 180)
    ->child(LayoutNode::leaf(null, new LabelSpec('Lists', size: 14.0), height: 22))
    ->child($comboLeaf)
    ->child($editComboLeaf)
    ->child($radioCol);

// Tab
$tabControl = new TabControl('gallery-tab', [
    ['id' => 'page1', 'label' => 'Page 1', 'content' => LayoutNode::leaf('tab-content-1', height: 80)],
    ['id' => 'page2', 'label' => 'Page 2', 'content' => LayoutNode::leaf('tab-content-2', height: 80)],
    ['id' => 'page3', 'label' => 'Page 3', 'content' => LayoutNode::leaf('tab-content-3', height: 80)],
]);

$rightCol = LayoutNode::column(gap: 12, padding: 0, align: LayoutStyle::ALIGN_STRETCH, width: 280, height: 480)
    ->child($numbersCol)
    ->child($listsCol)
    ->child(LayoutNode::leaf(null, new LabelSpec('Tab', size: 14.0), height: 22))
    ->child($tabControl->root());

// ═════════════════════════════════════════════════════════════════════════════
// MAIN LAYOUT
// ═════════════════════════════════════════════════════════════════════════════

$mainRow = LayoutNode::row(gap: 16, padding: 12, align: LayoutStyle::ALIGN_START, height: 540)
    ->child($leftCol)
    ->child($rightCol);

// ═════════════════════════════════════════════════════════════════════════════
// Surface + Events
// ═════════════════════════════════════════════════════════════════════════════

$surface = new Surface($mainRow);
$status = new Label('Control Gallery — 自绘版 · 点击控件聚焦 · Tab 切换 · Slider 拖拽');

// Button click
$surface->onClick('btn', static fn () => $status->setText('Button 被点击'));

// Checkbox toggle
$surface->onClick('chk', static function () use ($chkLeaf, $surface, $status): void {
    $s = $chkLeaf->spec;
    if (! $s instanceof CheckboxSpec) return;
    $chkLeaf->spec = new CheckboxSpec(checked: ! $s->checked, label: $s->label);
    $surface->redraw();
    $status->setText($chkLeaf->spec->checked ? '已勾选' : '已取消');
});

// Slider drag → updates progress
$surface->onDrag('slider', static function (float $x, float $y, float $w, float $h) use ($sliderLeaf, $progressLeaf, $surface, $status): void {
    $value = max(0.0, min(1.0, $w > 0 ? $x / $w : 0.0));
    $sliderLeaf->spec = new SliderSpec(value: $value, pressed: true);
    $progressLeaf->spec = new ProgressSpec(value: $value);
    $surface->redraw();
    $status->setText('Slider/Progress: ' . (int) round($value * 100) . '%');
});

// Number input — filter to digits only
$surface->onText('num', static function (string $char, bool $backspace) use ($numLeaf, $surface, $status): void {
    $s = $numLeaf->spec;
    if (! $s instanceof NumberSpec) return;
    $cur = $s->value;
    $next = $backspace ? mb_substr($cur, 0, -1) : $cur . $char;
    if (! preg_match('/^[\d.]*$/', $next)) return;
    $numLeaf->spec = new NumberSpec(value: $next, placeholder: '0–100', min: 0, max: 100);
    $surface->redraw();
    $status->setText('Number: ' . $next);
});

// Radio select
foreach ($radioOptions as $i => $label) {
    $surface->onClick("radio:{$i}", static function () use ($i, $radioOptions, $radioNodes, $surface, $status): void {
        foreach ($radioNodes as $j => $node) {
            if ($node === null) continue;
            $node->spec = new RadioSpec(selected: $j === $i, label: $radioOptions[$j]);
        }
        $surface->redraw();
        $status->setText("Radio: {$radioOptions[$i]}");
    });
}

// DatePicker click → open native date picker dialog
$surface->onClick('date', static function () use ($dateLeaf, $surface, &$mainWindow, $status): void {
    if ($mainWindow === null) return;
    $date = DatePickerDialog::pick($mainWindow);
    if ($date !== null) {
        $dateLeaf->spec = new DatePickerSpec(value: $date->format('Y-m-d'), placeholder: '选择日期…');
        $surface->redraw();
        $status->setText('Date: ' . $date->format('Y-m-d'));
    } else {
        $status->setText('DatePicker cancelled');
    }
});

// FilePicker click → open native file picker dialog
$surface->onClick('file', static function () use ($fileLeaf, $surface, &$mainWindow, $status): void {
    if ($mainWindow === null) return;
    $path = FilePickerDialog::pick($mainWindow);
    if ($path !== null) {
        $fileLeaf->spec = new FilePickerSpec(value: basename($path), placeholder: '选择文件…');
        $surface->redraw();
        $status->setText('File: ' . $path);
    } else {
        $status->setText('FilePicker cancelled');
    }
});

// Font / Color picker (native dialogs)
$mainWindow = null;

$surface->onClick('fontBtn', static function () use (&$mainWindow, $status): void {
    if ($mainWindow === null) return;
    $font = FontPickerDialog::pick($mainWindow);
    $status->setText($font !== null ? "Font: {$font->family()}, {$font->size()}pt" : 'Font picker cancelled');
});

$surface->onClick('colorBtn', static function () use (&$mainWindow, $status): void {
    if ($mainWindow === null) return;
    $color = ColorPickerDialog::pick($mainWindow);
    $status->setText($color !== null ? "Color: R={$color->r} G={$color->g} B={$color->b}" : 'Color picker cancelled');
});

// Tab change
$tabControl->bind($surface)->onChange(static function (int $i) use ($status): void {
    $status->setText('Tab: Page ' . ($i + 1));
});

// ═════════════════════════════════════════════════════════════════════════════
// Window
// ═════════════════════════════════════════════════════════════════════════════

$mainWindow = new Window('Control Gallery — 自绘版', 600, 560, false);
$mainWindow->setChild(Build::vbox(
    $status,
    Build::stretchy($surface->root()),
));

App::new()
    ->window($mainWindow)
    ->onShouldQuit(fn () => true)
    ->run();
