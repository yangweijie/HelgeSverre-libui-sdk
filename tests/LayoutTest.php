<?php

declare(strict_types=1);

use Libui\Control;
use Libui\Label;
use Yangweijie\Ui2\Layout\GroupSection;
use Yangweijie\Ui2\Layout\TabContainer;

// ---------------------------------------------------------------------------
// TabContainer
// ---------------------------------------------------------------------------

test('TabContainer can be constructed', function (): void {
    $tabs = new TabContainer();
    expect($tabs->root())->toBeInstanceOf(Control::class);
    expect($tabs->pageCount())->toBe(0);
});

test('TabContainer addPage increments page count', function (): void {
    $tabs = new TabContainer();
    $tabs->addPage('General', new Label('Content'));
    expect($tabs->pageCount())->toBe(1);
});

test('TabContainer addPage with margined', function (): void {
    $tabs = new TabContainer();
    $tabs->addPage('Settings', new Label('Content'), true);
    expect($tabs->pageCount())->toBe(1);
    expect($tabs->pageLabels())->toBe(['Settings']);
});

test('TabContainer addPage multiple pages', function (): void {
    $tabs = new TabContainer();
    $tabs->addPage('Tab A', new Label('A'));
    $tabs->addPage('Tab B', new Label('B'));
    $tabs->addPage('Tab C', new Label('C'));
    expect($tabs->pageCount())->toBe(3);
    expect($tabs->pageLabels())->toBe(['Tab A', 'Tab B', 'Tab C']);
});

test('TabContainer removePage decrements count', function (): void {
    $tabs = new TabContainer();
    $tabs->addPage('Tab A', new Label('A'));
    $tabs->addPage('Tab B', new Label('B'));
    $tabs->removePage(0);
    expect($tabs->pageCount())->toBe(1);
    expect($tabs->pageLabels())->toBe(['Tab B']);
});

test('TabContainer addPage returns static for chaining', function (): void {
    $tabs = new TabContainer();
    $result = $tabs->addPage('Tab', new Label('Content'));
    expect($result)->toBe($tabs);
});

test('TabContainer tab() returns the underlying Tab', function (): void {
    $tabs = new TabContainer();
    expect($tabs->tab())->toBeInstanceOf(\Libui\Tab::class);
});

// ---------------------------------------------------------------------------
// GroupSection
// ---------------------------------------------------------------------------

test('GroupSection can be constructed with title', function (): void {
    $group = new GroupSection('Settings');
    expect($group->root())->toBeInstanceOf(Control::class);
    expect($group->title())->toBe('Settings');
});

test('GroupSection can be constructed with child', function (): void {
    $group = new GroupSection('Settings', new Label('Content'));
    expect($group->root())->toBeInstanceOf(Control::class);
    expect($group->title())->toBe('Settings');
});

test('GroupSection setChild returns static for chaining', function (): void {
    $group = new GroupSection('Settings');
    $result = $group->setChild(new Label('Content'));
    expect($result)->toBe($group);
});

test('GroupSection setTitle returns static for chaining', function (): void {
    $group = new GroupSection('Settings');
    $result = $group->setTitle('New Title');
    expect($result)->toBe($group);
    expect($group->title())->toBe('New Title');
});
