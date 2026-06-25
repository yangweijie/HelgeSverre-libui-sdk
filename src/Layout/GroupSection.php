<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

use Libui\Control;
use Libui\Group;
use Yangweijie\Ui2\Composite;

/**
 * A convenience wrapper around {@see Group} that auto-pads its child,
 * manages the title, and accepts {@see Composite} children directly.
 *
 * The upstream Group patch already accepts Composite via setChild();
 * this wrapper adds auto-padding and a fluent API for setting the title.
 *
 * ```php
 * $section = new GroupSection('Settings');
 * $section->setChild($someField);
 * ```
 */
class GroupSection extends Composite
{
    private Group $group;

    public function __construct(string $title, Control|Composite|null $child = null)
    {
        $this->group = new Group($title);

        if ($child !== null) {
            $this->setChild($child);
        }
    }

    /**
     * Set the child of this group section.
     */
    public function setChild(Control|Composite $child): static
    {
        $this->group->setChild($child);
        return $this;
    }

    /**
     * Get the current title.
     */
    public function title(): string
    {
        return $this->group->title();
    }

    /**
     * Update the title.
     */
    public function setTitle(string $title): static
    {
        $this->group->setTitle($title);
        return $this;
    }

    public function root(): Control
    {
        return $this->group;
    }
}
