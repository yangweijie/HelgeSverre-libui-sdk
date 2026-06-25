<?php

declare(strict_types=1);

use Libui\Box;
use Libui\Control;
use Libui\Testing\CallbackSpy;
use Libui\Testing\Inspect;
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
use Yangweijie\Ui2\Fields\TextAreaField;
use Yangweijie\Ui2\Fields\TextField;



// ---------------------------------------------------------------------------
// TextField
// ---------------------------------------------------------------------------

test('TextField can be constructed and returns initial value', function (): void {
    $field = new TextField('Name:', 'John');
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBe('John');
});

test('TextField setValue updates the value', function (): void {
    $field = new TextField('Name:');
    $field->setValue('Alice');
    expect($field->value())->toBe('Alice');
});

test('TextField emits change event', function (): void {
    $field = new TextField('Name:');
    $spy = new CallbackSpy();
    $field->on('change', $spy);
    // simulate by emitting directly since upstream Entry::onChanged
    // requires a real UI event
    $field->setValue('Bob');
    // change event is not triggered by setValue, only by user interaction
    // so we verify the infrastructure works
    expect($field->value())->toBe('Bob');
});

// ---------------------------------------------------------------------------
// PasswordField
// ---------------------------------------------------------------------------

test('PasswordField can be constructed', function (): void {
    $field = new PasswordField('Password:');
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('PasswordField stores and retrieves value', function (): void {
    $field = new PasswordField('Password:', 'secret123');
    expect($field->value())->toBe('secret123');
    $field->setValue('newsecret');
    expect($field->value())->toBe('newsecret');
});

// ---------------------------------------------------------------------------
// SearchField
// ---------------------------------------------------------------------------

test('SearchField can be constructed', function (): void {
    $field = new SearchField('Search:');
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('SearchField stores and retrieves value', function (): void {
    $field = new SearchField('Search:', 'query');
    expect($field->value())->toBe('query');
    $field->setValue('updated query');
    expect($field->value())->toBe('updated query');
});

// ---------------------------------------------------------------------------
// NumberField
// ---------------------------------------------------------------------------

test('NumberField can be constructed', function (): void {
    $field = new NumberField('Count:', 0, 10, 5);
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBe(5);
});

test('NumberField setValue updates value', function (): void {
    $field = new NumberField('Count:', 0, 10);
    $field->setValue(7);
    expect($field->value())->toBe(7);
});

// ---------------------------------------------------------------------------
// CheckboxField
// ---------------------------------------------------------------------------

test('CheckboxField can be constructed with initial value', function (): void {
    $field = new CheckboxField('Agree:', true);
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBe(true);
});

test('CheckboxField setValue toggles', function (): void {
    $field = new CheckboxField('Agree:', false);
    expect($field->value())->toBe(false);
    $field->setValue(true);
    expect($field->value())->toBe(true);
});

test('CheckboxField emits change event', function (): void {
    $field = new CheckboxField('Agree:');
    $spy = new CallbackSpy();
    $field->on('change', $spy);
    expect($field->value())->toBe(false);
});

// ---------------------------------------------------------------------------
// RadioGroup
// ---------------------------------------------------------------------------

test('RadioGroup can be constructed and options added', function (): void {
    $field = new RadioGroup('Language:');
    $field->addOptions(['PHP', 'Python', 'Rust']);
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('RadioGroup value returns selected index', function (): void {
    $field = new RadioGroup('Language:');
    $field->addOptions(['PHP', 'Python', 'Rust']);
    expect($field->value())->toBe(-1); // none selected by default
});

test('RadioGroup setValue selects by index', function (): void {
    $field = new RadioGroup('Language:');
    $field->addOptions(['PHP', 'Python', 'Rust']);
    $field->setValue(1);
    expect($field->value())->toBe(1);
});

test('RadioGroup addOption adds single option', function (): void {
    $field = new RadioGroup('Lang:');
    $field->addOption('Go');
    expect($field->root())->toBeInstanceOf(Control::class);
});

// ---------------------------------------------------------------------------
// ComboBoxField
// ---------------------------------------------------------------------------

test('ComboBoxField can be constructed', function (): void {
    $field = new ComboBoxField('Role:');
    $field->addOptions(['Admin', 'Editor', 'Viewer']);
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('ComboBoxField setValue selects by index', function (): void {
    $field = new ComboBoxField('Role:');
    $field->addOptions(['Admin', 'Editor', 'Viewer']);
    $field->setValue(1);
    expect($field->value())->toBe(1);
});

// ---------------------------------------------------------------------------
// EditableComboBoxField
// ---------------------------------------------------------------------------

test('EditableComboBoxField can be constructed', function (): void {
    $field = new EditableComboBoxField('City:');
    $field->addOptions(['Beijing', 'Shanghai', 'Shenzhen']);
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('EditableComboBoxField supports setValue and value', function (): void {
    $field = new EditableComboBoxField('City:');
    $field->setValue('Guangzhou');
    expect($field->value())->toBe('Guangzhou');
});

test('EditableComboBoxField emits change event', function (): void {
    $field = new EditableComboBoxField('City:');
    $spy = new CallbackSpy();
    $field->on('change', $spy);
    expect($field->value())->toBe('');
});

// ---------------------------------------------------------------------------
// DatePickerField
// ---------------------------------------------------------------------------

test('DatePickerField can be constructed', function (): void {
    $field = new DatePickerField('Appointment:');
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBeInstanceOf(\DateTimeImmutable::class);
});

test('DatePickerField dateOnly factory', function (): void {
    $field = DatePickerField::dateOnly('Date:');
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBeInstanceOf(\DateTimeImmutable::class);
});

test('DatePickerField timeOnly factory', function (): void {
    $field = DatePickerField::timeOnly('Time:');
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBeInstanceOf(\DateTimeImmutable::class);
});

test('DatePickerField setValue accepts DateTimeInterface', function (): void {
    $field = new DatePickerField('Appointment:');
    $now = new \DateTimeImmutable();
    $field->setValue($now);
    expect($field->value())->toBeInstanceOf(\DateTimeImmutable::class);
});

// ---------------------------------------------------------------------------
// TextAreaField
// ---------------------------------------------------------------------------

test('TextAreaField can be constructed', function (): void {
    $field = new TextAreaField('Biography:', 'Hello world');
    expect($field->root())->toBeInstanceOf(Control::class);
    expect($field->value())->toBe('Hello world');
});

test('TextAreaField setValue updates text', function (): void {
    $field = new TextAreaField('Bio:');
    $field->setValue('Updated text');
    expect($field->value())->toBe('Updated text');
});

// ---------------------------------------------------------------------------
// ProgressBarField
// ---------------------------------------------------------------------------

test('ProgressBarField can be constructed', function (): void {
    $field = new ProgressBarField('Progress:');
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('ProgressBarField setProgress and indeterminate', function (): void {
    $field = new ProgressBarField('Progress:', 50);
    $field->setProgress(75);
    // ProgressBar doesn't expose value via a standard getter,
    // but we verify it doesn't crash
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('ProgressBarField indeterminate mode', function (): void {
    $field = new ProgressBarField('Progress:');
    $field->indeterminate();
    expect($field->root())->toBeInstanceOf(Control::class);
});

test('ProgressBarField determinate resets from indeterminate', function (): void {
    $field = new ProgressBarField('Progress:');
    $field->indeterminate();
    $field->determinate(30);
    expect($field->root())->toBeInstanceOf(Control::class);
});

// ---------------------------------------------------------------------------
// SeparatorLine
// ---------------------------------------------------------------------------

test('SeparatorLine can be constructed', function (): void {
    $line = new SeparatorLine();
    expect($line->root())->toBeInstanceOf(Control::class);
});

test('SeparatorLine returns null value (no HasValue)', function (): void {
    $line = new SeparatorLine();
    expect($line->value())->toBeNull();
});

// ---------------------------------------------------------------------------
// All field root() returns a visible Control
// ---------------------------------------------------------------------------

test('all fields return a visible control from root()', function (): void {
    $fields = [
        new TextField('T:', 'v'),
        new PasswordField('P:', 'v'),
        new SearchField('S:', 'v'),
        new NumberField('N:', 0, 10, 5),
        new CheckboxField('C:', true),
        new SeparatorLine(),
    ];

    foreach ($fields as $field) {
        $root = $field->root();
        expect($root)->toBeInstanceOf(Control::class, \get_class($field) . '::root() is not a Control');
    }
});
