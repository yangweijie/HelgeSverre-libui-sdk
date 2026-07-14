<?php

declare(strict_types=1);

namespace Libui;

use Yangweijie\Ui2\Composite;

/**
 * Group widget — a titled box with a single child.
 *
 * PATCHED: accepts Composite children via setChild(), following the same
 * pattern as the Box/Form/Grid patches.
 */
class Group extends Generated\Group
{
    /**
     * Set the group's child, unwrapping a Composite if needed.
     *
     * @param Control|Composite $child The child control or composite.
     * @return $this
     */
    public function setChild(Control|Composite $child): static
    {
        $control = $child instanceof Composite ? $child->root() : $child;
        $this->clearChildren();
        $this->registerChild($control);
        $control->setParentInternal($this);
        return parent::setChild($control);
    }

    /** Accessibility/automation node for this group and its child. */
    public function semantics(): ?\Yangweijie\Ui2\Semantics\SemanticsNode
    {
        $label = method_exists($this, 'title') ? $this->title() : null;
        return \Yangweijie\Ui2\Semantics\SemanticsNode::fromControls($this->children(), \Yangweijie\Ui2\Semantics\WidgetRole::Group, $label);
    }

    /**
     * Create a group wrapping the given child in one call.
     *
     * @param  string            $title The group's title.
     * @param  Control|Composite $child The child to wrap.
     * @return static
     */
    public static function titled(string $title, Control|Composite $child): static
    {
        $group = new static($title);
        $group->setChild($child);
        return $group;
    }
}
