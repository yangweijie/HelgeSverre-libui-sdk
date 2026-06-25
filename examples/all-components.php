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
use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;
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
use Yangweijie\Ui2\Pickers\DatePickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Pickers\TimePickerDialog;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\TableView;
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
// TAB 3 — Dialogs (MessageBox, DialogConfirm, DialogPrompt)
// =========================================================================

$dialogControls = Build::vbox(
    new Label('MessageBox — native info/warning/error dialogs:'),
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
    (new SeparatorLine())->root(),
    new Label('DialogConfirm — return true/false:'),
    Build::hbox(
        (new Button('Confirm Delete'))->onClicked(function () use (&$mainWindow, $outputLabel): void {
            $confirmed = DialogConfirm::ask($mainWindow, 'Delete', 'Delete this item?');
            $outputLabel->setText($confirmed ? 'User confirmed deletion' : 'User cancelled deletion');
        }),
        Build::stretchy(new Label('')),
    ),
    (new SeparatorLine())->root(),
    new Label('DialogPrompt — return ?string:'),
    Build::hbox(
        (new Button('Enter Name'))->onClicked(function () use (&$mainWindow, $outputLabel): void {
            $name = DialogPrompt::ask($mainWindow, 'Name', 'Enter your name:', 'Guest');
            $outputLabel->setText($name !== null ? "Hello, {$name}!" : 'Prompt cancelled');
        }),
        Build::stretchy(new Label('')),
    ),
    Build::stretchy(new Label('')),
);

// =========================================================================
// TAB 4 — Pickers (ColorPickerDialog, FontPickerDialog, DatePickerDialog, TimePickerDialog)
// =========================================================================

$colorSwatch = new Label('(click Pick Color)');
$fontPreview = new Label('(click Pick Font)');
$datePreview = new Label('(click Pick Date)');
$timePreview = new Label('(click Pick Time)');

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
    Group::titled('Date Picker', Build::vbox(
        new Label('Pick a date:'),
        Build::hbox(
            (new Button('Pick Date'))->onClicked(function () use (&$mainWindow, $datePreview, $outputLabel): void {
                $date = DatePickerDialog::pick($mainWindow);
                if ($date !== null) {
                    $datePreview->setText($date->format('Y-m-d'));
                    $outputLabel->setText('Date selected');
                } else {
                    $outputLabel->setText('Date picker cancelled');
                }
            }),
            $datePreview,
            Build::stretchy(new Label('')),
        ),
    )),
    Group::titled('Time Picker', Build::vbox(
        new Label('Pick a time:'),
        Build::hbox(
            (new Button('Pick Time'))->onClicked(function () use (&$mainWindow, $timePreview, $outputLabel): void {
                $time = TimePickerDialog::pick($mainWindow);
                if ($time !== null) {
                    $timePreview->setText($time->format('H:i'));
                    $outputLabel->setText('Time selected');
                } else {
                    $outputLabel->setText('Time picker cancelled');
                }
            }),
            $timePreview,
            Build::stretchy(new Label('')),
        ),
    )),
    Build::stretchy(new Label('')),
);

// =========================================================================
// TAB 5 — TableView (editable cells + sortable headers)
// =========================================================================

$table = new TableView(
    columns: ['Name', 'Age', 'Score'],
    rows: [
        ['Alice', 30, 95],
        ['Bob', 25, 87],
        ['Charlie', 35, 92],
    ],
    editable: [1, 2], // Age and Score are editable in-place
);
// Toggle sort on header click: click once = asc, click again = desc
$table->onHeaderClicked(function ($t, int $col) use ($table, $outputLabel): void {
    static $direction = [];
    $dir = ($direction[$col] ?? 'desc') === 'asc' ? 'desc' : 'asc';
    $direction[$col] = $dir;
    $table->sortByColumn($col, $dir);
    $outputLabel->setText('Sorted by ' . $col . ' ' . $dir);
});
$table->onRowClicked(function ($t, int $row) use ($outputLabel, $table): void {
    $rows = $table->selectedRows();
    $outputLabel->setText('Row ' . $row . ' clicked, ' . count($rows) . ' selected');
});

$tableControls = Build::vbox(
    Group::titled('Data Table (Age/Score editable — click headers to sort)',
        Build::vbox(
            $table->root(),
            Build::hbox(
                (new Button('Add Row'))->onClicked(function () use ($table, $outputLabel): void {
                    $table->addRow(['New', 0, 0]);
                    $outputLabel->setText('Row added (count: ' . $table->rowCount() . ')');
                }),
                (new Button('Remove Last'))->onClicked(function () use ($table, $outputLabel): void {
                    if ($table->rowCount() > 0) {
                        $table->removeRow($table->rowCount() - 1);
                        $outputLabel->setText('Last row removed');
                    }
                }),
                Build::stretchy(new Label('')),
            ),
        ),
    ),
);

// =========================================================================
// Window + Tab container
// =========================================================================

$tab = new Tab();
$tab->appendMargined('Fields', $fieldsBox);
$tab->appendMargined('Custom', $toggleControls);
$tab->appendMargined('Dialogs', $dialogControls);
$tab->appendMargined('Pickers', $pickerControls);
$tab->appendMargined('Table', $tableControls);

// Build the main layout: tab takes all vertical space, status bar sits at the bottom
$mainWindow = new Window('All Components — ui2 Demo', 560, 520, true);
$mainWindow->setChild(Build::vbox($tab, $outputLabel));

App::new()
    ->window($mainWindow)
    ->onShouldQuit(fn () => true)
    ->run();

// App::run() blocks until the window closes; code here runs after.
