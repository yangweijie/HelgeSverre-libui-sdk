# Widget Catalog

Complete reference for all ui2 widgets with usage examples.

---

## Fields (Form Inputs)

All fields follow the pattern: `Label + Input` composite widget.

### TextField
```php
use Yangweijie\Ui2\Fields\TextField;

$field = new TextField('Username:', 'placeholder text');
$field->onChange(fn($text) => print("Changed: $text"));
$field->setValue('default value');
$value = $field->getValue();
```

### PasswordField
```php
use Yangweijie\Ui2\Fields\PasswordField;

$field = new PasswordField('Password:');
$field->setValue('secret');
```

### TextAreaField
```php
use Yangweijie\Ui2\Fields\TextAreaField;

$field = new TextAreaField('Bio:', 5);  // 5 rows
$field->setValue("Line 1\nLine 2");
```

### NumberField
```php
use Yangweijie\Ui2\Fields\NumberField;

$field = new NumberField('Age:', 0, 120);  // min, max
$field->setValue(25);
$value = $field->getValue();  // returns int
```

### SearchField
```php
use Yangweijie\Ui2\Fields\SearchField;

$field = new SearchField('Search:');
$field->onChange(fn($query) => $results = search($query));
```

### ComboBoxField
```php
use Yangweijie\Ui2\Fields\ComboBoxField;

$field = new ComboBoxField('Color:', ['Red', 'Green', 'Blue']);
$field->onSelect(fn($index) => print("Selected: $index"));
$field->setSelected(1);  // Green
```

### EditableComboBoxField
```php
use Yangweijie\Ui2\Fields\EditableComboBoxField;

$field = new EditableComboBoxField('Tags:', ['php', 'js', 'rust']);
$field->onChange(fn($text) => print("Typed: $text"));
```

### CheckboxField
```php
use Yangweijie\Ui2\Fields\CheckboxField;

$field = new CheckboxField('Agree to terms');
$field->onToggle(fn($checked) => print($checked ? 'ON' : 'OFF'));
$field->setChecked(true);
```

### RadioGroup
```php
use Yangweijie\Ui2\Fields\RadioGroup;

$group = new RadioGroup('Plan:', ['Free', 'Pro', 'Enterprise']);
$group->onSelect(fn($index) => print("Plan: $index"));
$group->setSelected(1);  // Pro
```

### SliderField
```php
use Yangweijie\Ui2\Fields\SliderField;

$slider = new SliderField('Volume:', 0, 100);
$slider->onChange(fn($value) => $audio->setVolume($value));
$slider->setValue(50);
```

### ProgressBarField
```php
use Yangweijie\Ui2\Fields\ProgressBarField;

$progress = new ProgressBarField('Upload:');
$progress->setValue(0.75);  // 0.0 to 1.0
```

### DatePickerField
```php
use Yangweijie\Ui2\Fields\DatePickerField;

$field = new DatePickerField('Birthdate:');
$field->onChange(fn($date) => print("Date: $date"));  // Y-m-d format
```

### FilePickerField
```php
use Yangweijie\Ui2\Fields\FilePickerField;

$field = new FilePickerField('Select file:', 'Open', ['*.txt', '*.pdf']);
$field->onChange(fn($path) => print("File: $path"));
```

### SeparatorLine
```php
use Yangweijie\Ui2\Fields\SeparatorLine;

// Horizontal line
$sep = new SeparatorLine();

// Vertical line (in horizontal box)
$sep = SeparatorLine::vertical();
```

---

## Pickers (Modal Dialogs)

### ColorPickerDialog
```php
use Yangweijie\Ui2\Pickers\ColorPickerDialog;

$picker = new ColorPickerDialog($window, 'Pick Color', '#ff0000');
$picker->onSelect(fn($color) => $box->setColor($color));  // $color = '#rrggbb'
$picker->show();
```

### FontPickerDialog
```php
use Yangweijie\Ui2\Pickers\FontPickerDialog;

$picker = new FontPickerDialog($window, 'Pick Font');
$picker->onSelect(fn($font) => $label->setFont($font));  // $font = FontDescriptor
$picker->show();
```

### DatePickerDialog
```php
use Yangweijie\Ui2\Pickers\DatePickerDialog;

$picker = new DatePickerDialog($window, 'Select Date');
$picker->onSelect(fn($date) => print("Date: $date"));  // Y-m-d
$picker->show();
```

### TimePickerDialog
```php
use Yangweijie\Ui2\Pickers\TimePickerDialog;

$picker = new TimePickerDialog($window, 'Select Time');
$picker->onSelect(fn($time) => print("Time: $time"));  // H:i:s
$picker->show();
```

---

## Dialogs (Message Boxes)

### MessageBox
```php
use Yangweijie\Ui2\Dialogs\MessageBox;

MessageBox::info($window, 'Title', 'Information message');
MessageBox::warning($window, 'Warning', 'Something might be wrong');
MessageBox::error($window, 'Error', 'Something went wrong');
```

### DialogConfirm
```php
use Yangweijie\Ui2\Dialogs\DialogConfirm;

$dialog = new DialogConfirm($window, 'Confirm', 'Delete this item?');
$dialog->onConfirm(fn() => $item->delete());
$dialog->onCancel(fn() => print('Cancelled'));
$dialog->show();

// Dynamic sizing based on message length
$dialog = new DialogConfirm($window, 'Title', 'Very long message...');
$dialog->show();  // Auto-calculates width/height
```

### DialogPrompt
```php
use Yangweijie\Ui2\Dialogs\DialogPrompt;

$dialog = new DialogPrompt($window, 'Enter Name', 'Your name:', 'Default');
$dialog->onConfirm(fn($text) => print("Entered: $text"));
$dialog->onCancel(fn() => print('Cancelled'));
$dialog->show();
```

---

## Custom-Drawn Widgets (Area-based)

### CircleProgressBar
```php
use Yangweijie\Ui2\Widgets\CircleProgressBar;

$bar = new CircleProgressBar(200);  // diameter in pixels
$bar->setProgress(65);  // 0-100
$bar->setColor(0.2, 0.6, 1.0);  // RGB (0-1)
$bar->setThickness(8);
$bar->onChange(fn($progress) => print("Progress: $progress%"));

// In layout (needs stretchy for proper sizing):
$group = Group::titled('Progress',
    Build::vbox(
        Build::stretchy($bar->root()),
        new Label('65%')
    )
);
```

**Key APIs**:
- `setProgress(int $percent)` — 0-100
- `setColor(float $r, float $g, float $b, float $a = 1.0)`
- `getThickness(): int`
- `setThickness(int $px)`

### ToggleSwitch
```php
use Yangweijie\Ui2\Widgets\ToggleSwitch;

$switch = new ToggleSwitch('Enable feature');
$switch->onToggle(fn($on) => print($on ? 'ON' : 'OFF'));
$switch->setOn(true);
$isOn = $switch->isOn();
```

### StatusIndicator
```php
use Yangweijie\Ui2\Widgets\StatusIndicator;

$indicator = new StatusIndicator();
$indicator->setState(StatusIndicator::ON);      // Green
$indicator->setState(StatusIndicator::OFF);     // Gray
$indicator->setState(StatusIndicator::DIM);     // Yellow (warning)

// With label
$group = Group::titled('Status',
    Build::hbox($indicator->root(), new Label('Server Running'))
);
```

### Toast (Native Notifications)
```php
use Yangweijie\Ui2\Widgets\Toast;

// Static - no instance needed
Toast::show('Title', 'Message body');
Toast::show('Success', 'Saved!', Toast::ICON_SUCCESS);
Toast::show('Error', 'Failed!', Toast::ICON_ERROR);
Toast::show('Warning', 'Careful!', Toast::ICON_WARNING);
```

### TableView
```php
use Yangweijie\Ui2\Widgets\TableView;

$table = new TableView();
$table->setHeaders(['ID', 'Name', 'Email']);
$table->setRows([
    [1, 'John', 'john@example.com'],
    [2, 'Jane', 'jane@example.com'],
]);
$table->onRowClick(fn($rowIndex, $rowData) => print("Row $rowIndex: $rowData[1]"));
```

### TreeView (WebView-based)
```php
use Yangweijie\Ui2\Widgets\TreeView;

$tree = new TreeView($window, 300, 400);
$tree->setData([
    ['name' => 'src', 'type' => 'folder', 'children' => [
        ['name' => 'Widgets', 'type' => 'folder', 'children' => [
            ['name' => 'CircleProgressBar.php', 'type' => 'file'],
        ]],
    ]],
    ['name' => 'README.md', 'type' => 'file'],
]);
$tree->onNodeClick(fn($node) => print("Clicked: $node[name]"));
$tree->expandNode('src');
$tree->show();
```

### CodeEditor (WebView-based)
```php
use Yangweijie\Ui2\Widgets\CodeEditor;

$editor = new CodeEditor($window, 800, 600);
$editor->setCode('<?php echo "Hello"; ?>');
$editor->setLanguage('php');
$editor->onChange(fn($code) => $savedCode = $code);
$editor->show();
```

### SvgView
```php
use Yangweijie\Ui2\Widgets\SvgView;

$svg = new SvgView(400, 300);
$svg->loadFromFile('assets/logo.svg');
// or
$svg->loadFromString('<svg>...</svg>');

// Supported elements: rect, circle, ellipse, line, polygon, polyline, path, text, g
// Supported path commands: M/m, L/l, H/h, V/v, C/c, Q/q, A/a, Z
```

---

## Layout Containers

### TabContainer
```php
use Yangweijie\Ui2\Layout\TabContainer;

$tabs = new TabContainer([
    'Fields' => new TextField('Name:'),
    'Widgets' => new CircleProgressBar(150),
    'WebView' => new CodeEditor($window, 600, 400),
]);
$window->setChild($tabs->root());
```

### GroupSection
```php
use Yangweijie\Ui2\Layout\GroupSection;

$section = new GroupSection('Settings',
    Build::vbox(
        new TextField('API Key:'),
        new CheckboxField('Enable feature'),
    )
);
```

---

## Build Helpers

The `Build` class provides fluent layout construction:

```php
use Yangweijie\Ui2\Build;

// Vertical box
Build::vbox($child1, $child2, $child3)

// Horizontal box
Build::hbox($child1, $child2)

// Stretchy (takes remaining space)
Build::stretchy($areaOrComposite)

// Form (label + input pairs)
Build::form([
    'Name:' => new TextField('Name:'),
    'Email:' => new TextField('Email:'),
])

// Grid
Build::grid()
    ->place(0, 0, $widget1)
    ->place(0, 1, $widget2)
    ->place(1, 0, $widget3, 1, 2)  // row, col, widget, rowSpan, colSpan
    ->get()
```

---

## Quick Reference Table

| Widget | Namespace | Type | Key Methods |
|--------|-----------|------|-------------|
| TextField | Fields | Input | setValue(), getValue(), onChange() |
| NumberField | Fields | Input | setValue(), getValue() (int) |
| PasswordField | Fields | Input | setValue(), getValue() |
| TextAreaField | Fields | Input | setValue(), getValue() |
| SearchField | Fields | Input | onChange() |
| ComboBoxField | Fields | Select | setSelected(), onSelect() |
| EditableComboBoxField | Fields | Select+Input | setSelected(), onChange() |
| CheckboxField | Fields | Toggle | setChecked(), onToggle() |
| RadioGroup | Fields | Select | setSelected(), onSelect() |
| SliderField | Fields | Range | setValue(), onChange() |
| ProgressBarField | Fields | Display | setValue(0.0-1.0) |
| DatePickerField | Fields | Date | setValue(), onChange() |
| FilePickerField | Fields | File | onChange() |
| SeparatorLine | Fields | Visual | vertical() static |
| ColorPickerDialog | Pickers | Modal | show(), onSelect() |
| FontPickerDialog | Pickers | Modal | show(), onSelect() |
| DatePickerDialog | Pickers | Modal | show(), onSelect() |
| TimePickerDialog | Pickers | Modal | show(), onSelect() |
| MessageBox | Dialogs | Static | info(), warning(), error() |
| DialogConfirm | Dialogs | Modal | show(), onConfirm(), onCancel() |
| DialogPrompt | Dialogs | Modal | show(), onConfirm(), onCancel() |
| CircleProgressBar | Widgets | Custom | setProgress(), setColor(), setThickness() |
| ToggleSwitch | Widgets | Custom | setOn(), isOn(), onToggle() |
| StatusIndicator | Widgets | Custom | setState(ON/OFF/DIM) |
| Toast | Widgets | Static | show(title, message, icon?) |
| TableView | Widgets | Custom | setHeaders(), setRows(), onRowClick() |
| TreeView | Widgets | WebView | setData(), expandNode(), onNodeClick() |
| CodeEditor | Widgets | WebView | setCode(), setLanguage(), onChange() |
| SvgView | Widgets | Custom | loadFromFile(), loadFromString() |
| TabContainer | Layout | Container | - |
| GroupSection | Layout | Container | - |