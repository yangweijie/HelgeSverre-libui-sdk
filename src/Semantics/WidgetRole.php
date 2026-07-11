<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Semantics;

/**
 * ARIA-like semantic roles for self-drawn widgets.
 *
 * The native SDK the project is modelled on maps every widget to a
 * {@see WidgetRole} and exposes it on an accessibility ("semantics") tree that a
 * platform accessibility bridge can consume. The values mirror the ARIA role
 * vocabulary (button / checkbox / slider / textbox / combobox / group …) so the
 * mapping is obvious and portable.
 *
 * A role carries no state of its own — the *value* of that role (label, checked,
 * valueNow …) lives on the {@see SemanticsNode} produced from a layout tree.
 */
enum WidgetRole: string
{
    // Interactive controls
    case Button = 'button';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Slider = 'slider';
    case ProgressBar = 'progressbar';
    case TextBox = 'textbox';
    case ComboBox = 'combobox';

    // Composite / structural
    case Group = 'group';
    case List = 'list';
    case ListItem = 'listitem';
    case TabList = 'tablist';
    case Tab = 'tab';
    case Dialog = 'dialog';
    case MenuItem = 'menuitem';
    case Image = 'image';
}
