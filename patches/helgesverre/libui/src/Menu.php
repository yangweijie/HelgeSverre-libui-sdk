<?php

declare(strict_types=1);

namespace Libui;

use Libui\Exception\MenuOrderException;

/**
 * Menu widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\Menu.
 *
 * Enforces libui's "menus before the first window" rule, adds an inline
 * onClick variant on the append helpers, and provides a declarative fluent
 * builder API via {@see create()} / {@see item()} / {@see separator()} etc.
 *
 * ```php
 * Menu::create('File')
 *     ->item('Open…', fn($i) => $dialogs->openFile())
 *     ->separator()
 *     ->quitItem();
 * ```
 */
class Menu extends Generated\Menu
{
    public function __construct(string $name)
    {
        if (Window::menusLocked()) {
            $firstWindow = Window::firstWindowTitle();

            throw new MenuOrderException(
                \sprintf(
                    "Menu '%s' was created after a Window already exists. libui requires "
                    . 'every menu to be built BEFORE the first window. Move all `new Menu(...)` '
                    . 'calls above your first `new Window(...)`.',
                    $name,
                ),
                $firstWindow,
            );
        }
        parent::__construct($name);
    }

    /** Append a clickable item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendItem(string $name, ?callable $onClick = null): MenuItem
    {
        $item = MenuItem::fromGenerated(parent::appendItem($name));
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }

    /** Append a check item, optionally wiring a clean fn(MenuItem $item) handler. */
    public function appendCheckItem(string $name, ?callable $onClick = null): MenuItem
    {
        $item = MenuItem::fromGenerated(parent::appendCheckItem($name));
        if ($onClick !== null) {
            $item->onClick($onClick);
        }
        return $item;
    }

    /**
     * The platform Quit item, as a hand-wrapped {@see MenuItem} so `onClick()` is
     * available like every other append helper.
     */
    public function appendQuitItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendQuitItem());
    }

    /** The platform Preferences item, as a hand-wrapped {@see MenuItem}. */
    public function appendPreferencesItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendPreferencesItem());
    }

    /** The platform About item, as a hand-wrapped {@see MenuItem}. */
    public function appendAboutItem(): MenuItem
    {
        return MenuItem::fromGenerated(parent::appendAboutItem());
    }

    // -----------------------------------------------------------------------
    // Declarative / fluent builder API (return Menu, enabling chaining).
    //
    //     Menu::create('File')
    //         ->item('Open…', fn($i) => $dialogs->openFile())
    //         ->separator()
    //         ->quitItem();
    //
    // If you need the MenuItem instance later (e.g. enable/disable), use
    // the corresponding append*() method instead.
    // -----------------------------------------------------------------------

    /**
     * Create a menu with a declarative fluent API.
     *
     *     Menu::create('File')
     *         ->item('Open…', fn($i) => ...)
     *         ->separator()
     *         ->quitItem();
     *
     * @param  string  $name  Menu label (e.g. 'File', 'Edit', 'Help').
     * @return static         The new Menu, ready for chaining.
     */
    public static function create(string $name): static
    {
        return new static($name);
    }

    /** Fluent alias for {@see appendItem()}. Returns the Menu for chaining. */
    public function item(string $name, ?callable $onClick = null): static
    {
        $this->appendItem($name, $onClick);
        return $this;
    }

    /** Fluent alias for {@see appendCheckItem()}. Returns the Menu for chaining. */
    public function checkItem(string $name, ?callable $onClick = null): static
    {
        $this->appendCheckItem($name, $onClick);
        return $this;
    }

    /** Fluent alias for {@see appendSeparator()}. Returns the Menu for chaining. */
    public function separator(): static
    {
        $this->appendSeparator();
        return $this;
    }

    /** Fluent alias for {@see appendQuitItem()}. Returns the Menu for chaining. */
    public function quitItem(): static
    {
        $this->appendQuitItem();
        return $this;
    }

    /** Fluent alias for {@see appendPreferencesItem()}. Returns the Menu for chaining. */
    public function preferencesItem(): static
    {
        $this->appendPreferencesItem();
        return $this;
    }

    /** Fluent alias for {@see appendAboutItem()}. Returns the Menu for chaining. */
    public function aboutItem(): static
    {
        $this->appendAboutItem();
        return $this;
    }
}
