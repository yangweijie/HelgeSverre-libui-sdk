<?php

/**
 * Control Gallery — demonstrates every basic libui widget in a two-panel layout.
 *
 * Inspired by the classic libui control gallery.
 *
 * Run: php examples/control-gallery.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Checkbox;
use Libui\ColorButton;
use Libui\Combobox;
use Libui\DateTimePicker;
use Libui\EditableCombobox;
use Libui\FontButton;
use Libui\Group;
use Libui\Label;
use Libui\MultilineEntry;
use Libui\ProgressBar;
use Libui\RadioButtons;
use Libui\Separator;
use Libui\Slider;
use Libui\Spinbox;
use Libui\Tab;
use Libui\Window;

// ═════════════════════════════════════════════════════════════════════════════
// LEFT — Basic Controls
// ═════════════════════════════════════════════════════════════════════════════

$leftBox = Build::vbox(
    new Button('Button'),
    new Checkbox('Checkbox'),
    new Label('Label'),
    DateTimePicker::dateOnly(),
    DateTimePicker::timeOnly(),
    new DateTimePicker(),
    new FontButton(),
    new ColorButton(),
    new Separator(),
);

$leftGroup = Group::titled('Basic Controls', $leftBox);

// ═════════════════════════════════════════════════════════════════════════════
// RIGHT — Numbers, Lists, Tab panel
// ═════════════════════════════════════════════════════════════════════════════

// ── Numbers ──

$numbersBox = Build::vbox(
    new Spinbox(0, 100),
    new Slider(0, 100),
    new ProgressBar(),
);

$numbersGroup = Group::titled('Numbers', $numbersBox);

// ── Lists ──

$combo = new Combobox();
$combo->append('Combobox Item 1')
      ->append('Combobox Item 2')
      ->append('Combobox Item 3');

$editableCombo = new EditableCombobox();
$editableCombo->append('Editable Item 1')
              ->append('Editable Item 2')
              ->append('Editable Item 3');

$radio = new RadioButtons();
$radio->append('Radio Button 1')
      ->append('Radio Button 2')
      ->append('Radio Button 3');

$listsBox = Build::vbox($combo, $editableCombo, $radio);
$listsGroup = Group::titled('Lists', $listsBox);

// ── Tab panel ──

$tab = new Tab();
$tab->appendMargined('Page 1', new MultilineEntry());
$tab->appendMargined('Page 2', Build::vbox());
$tab->appendMargined('Page 3', Build::vbox());

$rightBox = Build::vbox(
    $numbersGroup,
    $listsGroup,
    $tab,
);

// ═════════════════════════════════════════════════════════════════════════════
// Main layout
// ═════════════════════════════════════════════════════════════════════════════

$mainBox = Build::hbox(
    Build::stretchy($leftGroup),
    Build::stretchy($rightBox),
);

// ═════════════════════════════════════════════════════════════════════════════
// Window
// ═════════════════════════════════════════════════════════════════════════════

$window = new Window('Control Gallery', 600, 500, false);
$window->setChild($mainBox);

App::new()
    ->window($window)
    ->onShouldQuit(fn() => true)
    ->run();
