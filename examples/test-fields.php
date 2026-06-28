<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Ffi;
use Libui\Group;
use Libui\Label;
use Libui\Window;

use Yangweijie\Ui2\Fields\CheckboxField;
use Yangweijie\Ui2\Fields\ComboBoxField;
use Yangweijie\Ui2\Fields\DatePickerField;
use Yangweijie\Ui2\Fields\EditableComboBoxField;
use Yangweijie\Ui2\Fields\FilePickerField;
use Yangweijie\Ui2\Fields\NumberField;
use Yangweijie\Ui2\Fields\PasswordField;
use Yangweijie\Ui2\Fields\ProgressBarField;
use Yangweijie\Ui2\Fields\RadioGroup;
use Yangweijie\Ui2\Fields\SearchField;
use Yangweijie\Ui2\Fields\SliderField;
use Yangweijie\Ui2\Fields\TextAreaField;
use Yangweijie\Ui2\Fields\TextField;

/**
 * Fields demo — all HasValue fields in a Form layout.
 *
 * Run: php85 examples/test-fields.php
 */

Ffi::init();

$window = new Window('Fields Demo', 800, 600, true);
$outputLabel = new Label('Interact with the controls above — events appear here.');

// ── Create all fields ──
$textField = new TextField('Name:', 'John Doe');
$searchField = new SearchField('Search:', '');
$passwordField = new PasswordField('Password:', '');
$numberField = new NumberField('Quantity:', 0, 100, 5);
$sliderField = new SliderField('Volume:', 0, 100);
$checkboxField = new CheckboxField('Enable feature');
$radioGroup = new RadioGroup('Theme:');
$comboBoxField = new ComboBoxField('Font Size:');
$editableComboBoxField = new EditableComboBoxField('City:');
$datePickerField = DatePickerField::dateOnly('Date:');
$textAreaField = new TextAreaField('Description:', '');
$progressBarField = new ProgressBarField('Progress:');

$radioGroup->addOptions(['Light', 'Dark', 'Auto']);
$comboBoxField->addOptions(['12px', '14px', '16px', '18px', '24px']);
$editableComboBoxField->addOptions(['Beijing', 'Shanghai', 'Shenzhen', 'Guangzhou']);

// ── Build Form with stretchy fields ──
$form = new \Libui\Form();
$form->setPadded(true);
$form->appendStretchy('Text', $textField);
$form->appendStretchy('Search', $searchField);
$form->appendStretchy('Password', $passwordField);
$form->appendStretchy('Number', $numberField);
$form->appendStretchy('Slider', $sliderField);
$form->appendStretchy('Checkbox', $checkboxField);
$form->appendStretchy('Radio', $radioGroup);
$form->appendStretchy('Combo', $comboBoxField);
$form->appendStretchy('Editable', $editableComboBoxField);
$form->appendStretchy('Date', $datePickerField);
$form->appendStretchy('Text Area', $textAreaField);
$form->appendStretchy('Progress', $progressBarField);
$form->appendStretchy('File', new FilePickerField($window, 'Browse…'));

// ── Wire up change events ──
$onChange = function (mixed $val) use ($outputLabel): void {
    $outputLabel->setText('Changed: ' . (is_bool($val) ? ($val ? 'true' : 'false') : (string) $val));
};
$textField->on('change', $onChange);
$searchField->on('change', $onChange);
$numberField->on('change', $onChange);
$sliderField->on('change', $onChange);
$checkboxField->on('change', $onChange);
$passwordField->on('change', fn () => $outputLabel->setText('Password changed'));
$radioGroup->on('change', fn (int $idx) => $outputLabel->setText("Theme index: {$idx}"));
$comboBoxField->on('change', fn (int $idx) => $outputLabel->setText("Font size index: {$idx}"));
$editableComboBoxField->on('change', fn (string $val) => $outputLabel->setText("City: {$val}"));
$datePickerField->on('change', fn (\DateTimeImmutable $dt) => $outputLabel->setText("Date: {$dt->format('Y-m-d')}"));
$textAreaField->on('change', fn () => $outputLabel->setText('Notes updated'));

// ── Action buttons ──
$readAllBtn = new \Libui\Button('Read All Fields');
$readAllBtn->onClicked(function () use ($datePickerField, $textAreaField, $textField, $numberField, $sliderField, $outputLabel): void {
    $lines = [
        'Text: ' . $textField->value(),
        'Number: ' . $numberField->value(),
        'Slider: ' . $sliderField->value(),
        'Date: ' . $datePickerField->value()->format('Y-m-d'),
        'Notes: ' . mb_substr($textAreaField->value(), 0, 30),
    ];
    $outputLabel->setText(implode(' | ', $lines));
});
$startProgressBtn = new \Libui\Button('Start Progress');
$startProgressBtn->onClicked(function () use ($progressBarField, $outputLabel): void {
    $progressBarField->indeterminate();
    $outputLabel->setText('Progress: indeterminate');
});

$buttonsBox = Build::hbox($readAllBtn, $startProgressBtn);

$window->setChild(Build::vbox(
    Group::titled('Input Fields', $form),
    $buttonsBox,
    $outputLabel,
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
