<?php

declare(strict_types=1);

use Yangweijie\Ui2\Layout\FlexLayout;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SelectSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Semantics\SemanticsNode;
use Yangweijie\Ui2\Semantics\WidgetRole;

test('mapType covers the widget catalogue', function (): void {
    expect(SemanticsNode::mapType('button'))->toBe(WidgetRole::Button);
    expect(SemanticsNode::mapType('checkbox'))->toBe(WidgetRole::Checkbox);
    expect(SemanticsNode::mapType('radio'))->toBe(WidgetRole::Radio);
    expect(SemanticsNode::mapType('slider'))->toBe(WidgetRole::Slider);
    expect(SemanticsNode::mapType('progress'))->toBe(WidgetRole::ProgressBar);
    expect(SemanticsNode::mapType('text_field'))->toBe(WidgetRole::TextBox);
    expect(SemanticsNode::mapType('select'))->toBe(WidgetRole::ComboBox);
    expect(SemanticsNode::mapType('card'))->toBe(WidgetRole::Group);
    expect(SemanticsNode::mapType('widget_of_the_week'))->toBe(WidgetRole::Group);
});

test('builds a semantics tree from a layout tree', function (): void {
    $root = LayoutNode::column(id: 'form')
        ->child(LayoutNode::leaf('save', new ButtonSpec('Save')))
        ->child(LayoutNode::leaf('agree', new CheckboxSpec(label: 'Agree', checked: true)))
        ->child(LayoutNode::leaf('vol', new SliderSpec(value: 0.5)))
        ->child(LayoutNode::leaf('name', new TextFieldSpec(placeholder: 'Name', value: 'Jo')))
        ->child(LayoutNode::leaf('pick', new SelectSpec(placeholder: 'Pick')))
        ->child(LayoutNode::leaf('bar', new ProgressSpec(value: 0.3)))
        ->child(
            LayoutNode::row(id: 'tabs')->withRole(WidgetRole::TabList)
                ->child(LayoutNode::leaf('tab1', new ButtonSpec('Tab 1'))->withRole(WidgetRole::Tab))
                ->child(LayoutNode::leaf('tab2', new ButtonSpec('Tab 2'))->withRole(WidgetRole::Tab))
        );

    // arbitrary layout so geometry is populated before building semantics
    FlexLayout::layout($root, 0, 0, 300, 400);

    $sem = SemanticsNode::fromLayout($root);

    expect($sem->role)->toBe(WidgetRole::Group);
    expect($sem->id)->toBe('form');

    $roles = array_map(fn (SemanticsNode $c) => $c->role, $sem->children);
    expect($roles)->toBe([
        WidgetRole::Button,
        WidgetRole::Checkbox,
        WidgetRole::Slider,
        WidgetRole::TextBox,
        WidgetRole::ComboBox,
        WidgetRole::ProgressBar,
        WidgetRole::TabList, // the row container with explicit role
    ]);

    // checkbox state lifted
    $checkbox = $sem->children[1];
    expect($checkbox->checked)->toBeTrue();
    expect($checkbox->label)->toBe('Agree');
    expect($checkbox->enabled)->toBeTrue();

    // slider range value
    $slider = $sem->children[2];
    expect($slider->valueNow)->toBe(0.5);
    expect($slider->value)->toBe('0.5');

    // textfield uses placeholder as label, carries its value
    $tf = $sem->children[3];
    expect($tf->label)->toBe('Name');
    expect($tf->value)->toBe('Jo');

    // progress range
    $bar = $sem->children[5];
    expect($bar->valueNow)->toBe(0.3);

    // geometry copied onto the leaf
    $save = $sem->children[0];
    expect($save->w)->toBe($root->children[0]->w);
    expect($save->x)->toBe($root->children[0]->x);

    // explicit role overrides derived, and nested roles are preserved
    $tabs = $sem->children[6];
    expect($tabs->role)->toBe(WidgetRole::TabList);
    expect($tabs->children[0]->role)->toBe(WidgetRole::Tab);
    expect($tabs->children[1]->role)->toBe(WidgetRole::Tab);
});

test('focusable is true only for id+spec leaves', function (): void {
    $root = LayoutNode::column()
        ->child(LayoutNode::leaf('a', new ButtonSpec('A')))        // focusable
        ->child(LayoutNode::leaf(null, new ButtonSpec('B')))       // no id → not focusable
        ->child(LayoutNode::leaf('container', null));              // container → not focusable

    $sem = SemanticsNode::fromLayout($root);

    expect($sem->children[0]->focusable)->toBeTrue();
    expect($sem->children[1]->focusable)->toBeFalse();
    expect($sem->children[2]->focusable)->toBeFalse();
});

test('disabled widgets expose enabled=false', function (): void {
    $root = LayoutNode::leaf('x', new ButtonSpec('X', enabled: false));
    $sem = SemanticsNode::fromLayout($root);

    expect($sem->enabled)->toBeFalse();
});
