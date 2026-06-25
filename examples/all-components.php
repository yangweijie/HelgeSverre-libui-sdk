<?php

declare(strict_types=1);

/**
 * All-components demo — demonstrates every Field, Widget, and Dialog
 * from the yangweijie/ui2 convenience layer.
 *
 * Run: php examples/all-components.php
 *
 * Layout: A Tab control with 4 tabs:
 *   - "Fields" — all 12 field composites
 *   - "Custom" — ToggleSwitch, StatusIndicator
 *   - "Dialogs" — MessageBox helpers
 *   - "Pickers" — ColorPickerDialog, FontPickerDialog
 *
 * @see patches/helgesverre/libui/src/Form.php  (values/setValues)
 * @see patches/helgesverre/libui/src/Group.php (titled factory)
 * @see patches/helgesverre/libui/src/Tab.php   (Composite children)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Box;
use Libui\Button;
use Libui\Build;
use Libui\Color;
use Libui\Ffi;
use Libui\Form;
use Libui\Group;
use Libui\Label;
use Libui\Tab;
use Libui\Window;
use Yangweijie\Ui2\Dialogs\MessageBox;
use Yangweijie\Ui2\Fields\CheckboxField;
use Yangweijie\Ui2\Fields\ComboBoxField;
use Yangweijie\Ui2\Fields\DatePickerField;
use Yangweijie\Ui2\Fields\EditableComboBoxField;
use Yangweijie\Ui2\Fields\NumberField;
use Yangweijie\Ui2\Fields\PasswordField;
use Yangweijie\Ui2\Fields\ProgressBarField;
use Yangweijie\Ui2\Fields\RadioGroup;
use Yangweijie\Ui2\Fields\SearchField;
use Yangweijie\Ui2\Fields\SeparatorLine;
use Yangweijie\Ui2\Fields\SliderField;
use Yangweijie\Ui2\Fields\TextAreaField;
use Yangweijie\Ui2\Fields\TextField;
use Yangweijie\Ui2\Pickers\ColorPickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\ToggleSwitch;

Ffi::init();

// =========================================================================
// Shared state
// =========================================================================

$outputLabel = new Label('Interact with the controls above — events appear here.');
$mainWindow = null; // will be set after window construction

// =========================================================================
// TAB 1 — All Field Composites
// =========================================================================

$textField = new TextField('Text:', 'Hello');
$passwordField = new PasswordField('Password:');
$searchField = new SearchField('Search:', 'libui');
$numberField = new NumberField('Count:', 0, 100, 42);
$sliderField = new SliderField('Volume:', 0, 100, 75);
$checkboxField = new CheckboxField('Dark Mode:', true);
$radioGroup = new RadioGroup('Color Theme:');
$radioGroup->addOptions(['System', 'Light', 'Dark']);
$comboBoxField = new ComboBoxField('Font Size:');
$comboBoxField->addOptions(['Small', 'Medium', 'Large']);
$editableComboBoxField = new EditableComboBoxField('Custom City:');
$editableComboBoxField->addOptions(['Beijing', 'Shanghai', 'Shenzhen']);
$datePickerField = DatePickerField::dateOnly('Appointment Date:');
$textAreaField = new TextAreaField('Notes:', 'Write your notes here...');
$progressBarField = new ProgressBarField('Download:', 0);
$separatorLine = new SeparatorLine();

// Wire up events to the shared output label
$onChange = function (mixed $val) use ($outputLabel): void {
    $outputLabel->setText('Changed: ' . (is_bool($val) ? ($val ? 'true' : 'false') : (string) $val));
};

$textField->on('change', $onChange);
$passwordField->on('change', fn () => $outputLabel->setText('Password changed'));
$searchField->on('change', $onChange);
$numberField->on('change', $onChange);
$sliderField->on('change', $onChange);
$checkboxField->on('change', $onChange);
$radioGroup->on('change', fn (int $idx) => $outputLabel->setText("Theme index: {$idx}"));
$comboBoxField->on('change', fn (int $idx) => $outputLabel->setText("Font size index: {$idx}"));
$editableComboBoxField->on('change', fn (string $val) => $outputLabel->setText("City: {$val}"));
$datePickerField->on('change', fn (\DateTimeImmutable $dt) => $outputLabel->setText("Date: {$dt->format('Y-m-d')}"));
$textAreaField->on('change', fn () => $outputLabel->setText('Notes updated'));

// Build the form
$fieldsForm = new Form();
$fieldsForm->setPadded(true);
$fieldsForm->append('Text', $textField->root());
$fieldsForm->append('Password', $passwordField->root());
$fieldsForm->append('Search', $searchField->root());
$fieldsForm->append('Number', $numberField->root());
$fieldsForm->append('Slider', $sliderField->root());
$fieldsForm->append('Checkbox', $checkboxField->root());
$fieldsForm->append('Radio', $radioGroup->root());
$fieldsForm->append('Combo', $comboBoxField->root());
$fieldsForm->append('Editable Combo', $editableComboBoxField->root());
$fieldsForm->append('Date Picker', $datePickerField->root());
$fieldsForm->append('Progress', $progressBarField->root());
// TextAreaField has its own internal label — just use appendStretchy
$fieldsForm->appendStretchy('', $textAreaField->root());

// Group of Fields
$fieldsGroup = Group::titled('All Field Composites', $fieldsForm);

// Buttons row
$buttonsBox = Build::hbox(
    Build::stretchy(new Label('')),
    (new Button('Read Values'))->onClicked(function () use (
        $textField, $checkboxField, $radioGroup, $comboBoxField, $editableComboBoxField,
        $datePickerField, $textAreaField, $numberField, $sliderField, $outputLabel
    ): void {
        $lines = [
            "Text: {$textField->value()}",
            "Number: {$numberField->value()}",
            "Slider: {$sliderField->value()}",
            "Checkbox: " . ($checkboxField->value() ? 'ON' : 'OFF'),
            "Theme (index): {$radioGroup->value()}",
            "Font (index): {$comboBoxField->value()}",
            "City: {$editableComboBoxField->value()}",
            "Date: {$datePickerField->value()->format('Y-m-d')}",
            "Notes: " . mb_substr($textAreaField->value(), 0, 30) . '...',
        ];
        $outputLabel->setText(implode(' | ', $lines));
    }),
    (new Button('Start Progress'))->onClicked(function () use ($progressBarField, $outputLabel): void {
        $progressBarField->indeterminate();
        $outputLabel->setText('Progress: indeterminate');
    }),
);
// File picker is separate as it needs the Window
$filePicker = null; // Window-dependant, set below

$fieldsBox = Build::vbox($fieldsGroup, $buttonsBox);

// =========================================================================
// TAB 2 — Custom Widgets (ToggleSwitch, StatusIndicator)
// =========================================================================

$toggle = new ToggleSwitch(false);
$toggle->on('change', fn (bool $on) => $outputLabel->setText($on ? 'Toggle: ON' : 'Toggle: OFF'));

$statusGreen = new StatusIndicator(Color::rgb(0x22C55E));
$statusRed = new StatusIndicator(Color::rgb(0xEF4444));
$statusYellow = new StatusIndicator(Color::rgb(0xEAB308));

$toggleControls = Build::vbox(
    Group::titled('Toggle Switch',
        Build::hbox(new Label('Enable feature:'), $toggle->root(), Build::stretchy(new Label(''))),
    ),
    Group::titled('Status Indicators',
        Build::hbox(
            new Label('Online:'),
            $statusGreen->root(),
            new Label('   '),
            new Label('Offline:'),
            $statusRed->root(),
            new Label('   '),
            new Label('Warning:'),
            $statusYellow->root(),
            Build::stretchy(new Label('')),
        ),
    ),
    (new Button('Toggle Status'))->onClicked(function () use ($statusGreen, $statusRed, $outputLabel): void {
        static $which = false;
        $which = !$which;
        if ($which) {
            $statusRed->setColorHex(0x22C55E);
            $statusGreen->setColorHex(0xEF4444);
            $outputLabel->setText('Status: swapped');
        } else {
            $statusGreen->setColorHex(0x22C55E);
            $statusRed->setColorHex(0xEF4444);
            $outputLabel->setText('Status: restored');
        }
    }),
    Build::stretchy(new Label('')),
);

// =========================================================================
// TAB 3 — Dialogs (MessageBox)
// =========================================================================

$dialogControls = Build::vbox(
    new Label('Click a button to show a native dialog:'),
    Build::hbox(
        (new Button('Info'))->onClicked(function () use (&$mainWindow, $outputLabel): void {
            MessageBox::info($mainWindow, 'Info', 'This is an information dialog.');
            $outputLabel->setText('Info dialog closed');
        }),
        (new Button('Warning'))->onClicked(function () use (&$mainWindow, $outputLabel): void {
            MessageBox::warning($mainWindow, 'Warning', 'This is a warning dialog.');
            $outputLabel->setText('Warning dialog closed');
        }),
        (new Button('Error'))->onClicked(function () use (&$mainWindow, $outputLabel): void {
            MessageBox::error($mainWindow, 'Error', 'This is an error dialog.');
            $outputLabel->setText('Error dialog closed');
        }),
        Build::stretchy(new Label('')),
    ),
    Build::stretchy(new Label('')),
);

// =========================================================================
// TAB 4 — Pickers (ColorPickerDialog, FontPickerDialog)
// =========================================================================

$colorSwatch = new Label('(click Pick Color)');
$fontPreview = new Label('(click Pick Font)');

$pickerControls = Build::vbox(
    Group::titled('Color Picker', Build::vbox(
        new Label('Pick a color from the native dialog:'),
        Build::hbox(
            (new Button('Pick Color'))->onClicked(function () use (&$mainWindow, $colorSwatch, $outputLabel): void {
                $color = ColorPickerDialog::pick($mainWindow);
                if ($color !== null) {
                    $colorSwatch->setText("R={$color->r} G={$color->g} B={$color->b}");
                    $outputLabel->setText('Color selected');
                } else {
                    $outputLabel->setText('Color picker cancelled');
                }
            }),
            $colorSwatch,
            Build::stretchy(new Label('')),
        ),
    )),
    Group::titled('Font Picker', Build::vbox(
        new Label('Pick a font from the native dialog:'),
        Build::hbox(
            (new Button('Pick Font'))->onClicked(function () use (&$mainWindow, $fontPreview, $outputLabel): void {
                $font = FontPickerDialog::pick($mainWindow);
                if ($font !== null) {
                        $fontPreview->setText($font->family() . ', ' . $font->size() . 'pt');
                    $outputLabel->setText('Font selected');
                } else {
                    $fontPreview->setText('cancelled');
                }
            }),
            $fontPreview,
            Build::stretchy(new Label('')),
        ),
    )),
    Build::stretchy(new Label('')),
);

// =========================================================================
// Window + Tab container
// =========================================================================

$tab = new Tab();
$tab->appendMargined('Fields', $fieldsBox);
$tab->appendMargined('Custom', $toggleControls);
$tab->appendMargined('Dialogs', $dialogControls);
$tab->appendMargined('Pickers', $pickerControls);

// Build the main layout: tab takes all vertical space, status bar sits at the bottom
$mainWindow = new Window('All Components — ui2 Demo', 560, 520, true);
$mainWindow->setChild(Build::vbox($tab, $outputLabel));

App::new()
    ->window($mainWindow)
    ->onShouldQuit(fn () => true)
    ->run();

// App::run() blocks until the window closes; code here runs after.
