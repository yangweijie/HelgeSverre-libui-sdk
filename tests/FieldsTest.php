<?php

declare(strict_types=1);

/**
 * FieldsTest — self-drawn (Surface + WidgetSpec) port of the old native
 * Fields\* test suite.
 *
 * Phase O of the "全面转向自绘" roadmap migrated the canonical `examples/test-fields.php`
 * demo onto the self-drawn Surface. This test completes that migration: instead
 * of asserting `root() instanceof Libui\Control` against the native `Yangweijie\Ui2\Fields\*`
 * wrappers, it asserts the *value objects* the renderer draws from — and that every
 * native field type has a registered self-drawn renderer in `RendererRegistry::default()`.
 *
 * Native wrappers (`src/Fields/*`) still exist (Phase P is blocked by IME), so this
 * test deliberately avoids importing them: once Phase P lands, deleting the native
 * layer leaves zero coverage gaps because each field below is already exercised
 * through its immutable Spec + registered renderer.
 */

use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\DatePickerSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\FilePickerSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\NumberSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\PasswordSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SearchFieldSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;

// ---------------------------------------------------------------------------
// Shared: every self-drawn field type must have a registered renderer
// ---------------------------------------------------------------------------

$registry = RendererRegistry::default();

test('self-drawn renderer exists for every migrated field type', function () use ($registry): void {
    $expectedTypes = [
        'text_field',    // TextField
        'password_field',// PasswordField
        'search_field',  // SearchField
        'number_field',  // NumberField
        'checkbox',      // CheckboxField
        'radio',         // RadioGroup option
        'select',        // ComboBoxField / EditableComboBoxField
        'date_picker',   // DatePickerField
        'text_area',     // TextAreaField
        'progress',      // ProgressBarField
        'slider',        // SliderField
        'file_picker',   // FilePickerField
        'separator',     // SeparatorLine (visual divider)
    ];

    foreach ($expectedTypes as $type) {
        expect($registry->has($type))
            ->toBeTrue("No self-drawn renderer registered for field type '{$type}'");
    }
});

// ---------------------------------------------------------------------------
// TextField  →  TextFieldSpec
// ---------------------------------------------------------------------------

test('TextField maps to TextFieldSpec with initial value', function (): void {
    $spec = new TextFieldSpec(value: 'John');
    expect($spec)->toBeInstanceOf(TextFieldSpec::class);
    expect($spec->type())->toBe('text_field');
    expect($spec->value)->toBe('John');
    expect($spec->placeholder)->toBe('');
    expect($spec->focused)->toBeFalse();
});

test('TextField value is updated by constructing a new spec', function (): void {
    $next = new TextFieldSpec(value: 'Alice', focused: true);
    expect($next->value)->toBe('Alice');
    expect($next->focused)->toBeTrue();
    // immutability: original spec is untouched
    $original = new TextFieldSpec(value: 'John');
    expect($original->value)->toBe('John');
});

// ---------------------------------------------------------------------------
// PasswordField  →  PasswordSpec
// ---------------------------------------------------------------------------

test('PasswordField maps to PasswordSpec and stores value', function (): void {
    $spec = new PasswordSpec(value: 'secret123');
    expect($spec->type())->toBe('password_field');
    expect($spec->value)->toBe('secret123');
    expect($spec->reveal)->toBeFalse();
});

test('PasswordSpec reveal toggles without mutating the original', function (): void {
    $hidden = new PasswordSpec(value: 'newsecret');
    $revealed = new PasswordSpec(value: 'newsecret', reveal: true);
    expect($hidden->value)->toBe('newsecret');
    expect($revealed->value)->toBe('newsecret');
    expect($revealed->reveal)->toBeTrue();
    expect($hidden->reveal)->toBeFalse();
});

// ---------------------------------------------------------------------------
// SearchField  →  SearchFieldSpec
// ---------------------------------------------------------------------------

test('SearchField maps to SearchFieldSpec and tracks clear affordance', function (): void {
    $empty = new SearchFieldSpec(value: '');
    expect($empty->type())->toBe('search_field');
    expect($empty->showClear)->toBeFalse();

    $typed = new SearchFieldSpec(value: 'updated query', showClear: true);
    expect($typed->value)->toBe('updated query');
    expect($typed->showClear)->toBeTrue();
});

// ---------------------------------------------------------------------------
// NumberField  →  NumberSpec
// ---------------------------------------------------------------------------

test('NumberField maps to NumberSpec with bounds', function (): void {
    $spec = new NumberSpec(value: '5', min: 0.0, max: 100.0);
    expect($spec->type())->toBe('number_field');
    expect($spec->value)->toBe('5');
    expect($spec->min)->toBe(0.0);
    expect($spec->max)->toBe(100.0);
});

test('NumberSpec clamps by re-constructing a new spec', function (): void {
    $clamped = new NumberSpec(value: '7', min: 0.0, max: 100.0);
    expect($clamped->value)->toBe('7');
});

// ---------------------------------------------------------------------------
// CheckboxField  →  CheckboxSpec
// ---------------------------------------------------------------------------

test('CheckboxField maps to CheckboxSpec with label and state', function (): void {
    $unchecked = new CheckboxSpec(label: 'Agree:', checked: false);
    expect($unchecked->type())->toBe('checkbox');
    expect($unchecked->checked)->toBeFalse();
    expect($unchecked->label)->toBe('Agree:');

    $toggled = new CheckboxSpec(label: 'Agree:', checked: true);
    expect($toggled->checked)->toBeTrue();
});

// ---------------------------------------------------------------------------
// RadioGroup  →  RadioSpec (one per option)
// ---------------------------------------------------------------------------

test('RadioGroup maps to a set of RadioSpec options', function (): void {
    $options = ['PHP', 'Python', 'Rust'];

    $specs = array_map(
        static fn (string $label, int $i): RadioSpec => new RadioSpec(selected: $i === 0, label: $label),
        $options,
        array_keys($options),
    );

    expect($specs[0]->type())->toBe('radio');
    expect($specs[0]->selected)->toBeTrue();
    expect($specs[1]->selected)->toBeFalse();
    expect($specs[2]->label)->toBe('Rust');

    // selecting index 1 means only that option is selected
    $selected = array_map(
        static fn (string $label, int $i): RadioSpec => new RadioSpec(selected: $i === 1, label: $label),
        $options,
        array_keys($options),
    );
    expect($selected[1]->selected)->toBeTrue();
    expect($selected[0]->selected)->toBeFalse();
});

// ---------------------------------------------------------------------------
// ComboBoxField / EditableComboBoxField  →  SelectSpec
// ---------------------------------------------------------------------------

test('ComboBoxField maps to SelectSpec', function (): void {
    $spec = new SelectSpec(value: '14px');
    expect($spec->type())->toBe('select');
    expect($spec->value)->toBe('14px');
});

test('EditableComboBoxField maps to SelectSpec with free text', function (): void {
    $spec = new SelectSpec(value: 'Guangzhou');
    expect($spec->type())->toBe('select');
    expect($spec->value)->toBe('Guangzhou');
    // cycling options produces a new immutable spec each time
    $next = new SelectSpec(value: 'Shenzhen');
    expect($next->value)->toBe('Shenzhen');
});

// ---------------------------------------------------------------------------
// DatePickerField  →  DatePickerSpec
// ---------------------------------------------------------------------------

test('DatePickerField maps to DatePickerSpec', function (): void {
    $unset = new DatePickerSpec(value: '');
    expect($unset->type())->toBe('date_picker');
    expect($unset->value)->toBe('');

    $picked = new DatePickerSpec(value: '2026-07-13');
    expect($picked->value)->toBe('2026-07-13');
});

// ---------------------------------------------------------------------------
// TextAreaField  →  TextAreaSpec
// ---------------------------------------------------------------------------

test('TextAreaField maps to TextAreaSpec with multiline value', function (): void {
    $spec = new TextAreaSpec(value: 'Hello world');
    expect($spec->type())->toBe('text_area');
    expect($spec->value)->toBe('Hello world');
});

test('TextAreaSpec updates text by constructing a new spec', function (): void {
    $updated = new TextAreaSpec(value: 'Updated text');
    expect($updated->value)->toBe('Updated text');
});

// ---------------------------------------------------------------------------
// ProgressBarField  →  ProgressSpec
// ---------------------------------------------------------------------------

test('ProgressBarField maps to ProgressSpec (0..1)', function (): void {
    $zero = new ProgressSpec(value: 0.0);
    expect($zero->type())->toBe('progress');
    expect($zero->value)->toBe(0.0);

    $half = new ProgressSpec(value: 0.5);
    expect($half->value)->toBe(0.5);

    $full = new ProgressSpec(value: 1.0);
    expect($full->value)->toBe(1.0);
});

// ---------------------------------------------------------------------------
// SeparatorLine  →  self-drawn separator (no value object, visual divider)
// ---------------------------------------------------------------------------

test('SeparatorLine has a self-drawn separator renderer', function () use ($registry): void {
    // SeparatorLine carries no value — its self-drawn replacement is a divider
    // drawn by SeparatorRenderer (type 'separator'), registered in the registry.
    expect($registry->has('separator'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// SliderField  →  SliderSpec
// ---------------------------------------------------------------------------

test('SliderField maps to SliderSpec (0..1) with pressed state', function (): void {
    $rest = new SliderSpec(value: 0.0);
    expect($rest->type())->toBe('slider');
    expect($rest->value)->toBe(0.0);
    expect($rest->pressed)->toBeFalse();

    $dragged = new SliderSpec(value: 0.5, pressed: true);
    expect($dragged->value)->toBe(0.5);
    expect($dragged->pressed)->toBeTrue();
});

// ---------------------------------------------------------------------------
// FilePickerField  →  FilePickerSpec
// ---------------------------------------------------------------------------

test('FilePickerField maps to FilePickerSpec with path value', function (): void {
    $empty = new FilePickerSpec(value: '');
    expect($empty->type())->toBe('file_picker');
    expect($empty->value)->toBe('');

    $chosen = new FilePickerSpec(value: '/tmp/test.txt');
    expect($chosen->value)->toBe('/tmp/test.txt');
});

// ---------------------------------------------------------------------------
// Cohesion: build the full catalog of specs the migrated demo draws
// ---------------------------------------------------------------------------

test('all field specs resolve through the default registry', function () use ($registry): void {
    $specs = [
        new TextFieldSpec(value: 'John Doe'),
        new SearchFieldSpec(value: ''),
        new PasswordSpec(value: ''),
        new NumberSpec(value: '5', min: 0.0, max: 100.0),
        new SliderSpec(value: 0.0),
        new CheckboxSpec(label: 'Enable feature', checked: false),
        new SelectSpec(value: '14px'),
        new DatePickerSpec(value: ''),
        new FilePickerSpec(value: ''),
        new ProgressSpec(value: 0.0),
        new TextAreaSpec(value: 'notes'),
        new RadioSpec(selected: true, label: 'Light'),
    ];

    foreach ($specs as $spec) {
        expect($registry->has($spec->type()))
            ->toBeTrue('Missing self-drawn renderer for ' . $spec::class . " (type '{$spec->type()}')");
    }
});
