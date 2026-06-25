<?php

declare(strict_types=1);

namespace Libui;

use Yangweijie\Ui2\Composite;

/**
 * Tab widget — a multi-page container with labelled tabs.
 *
 * PATCHED: append() and appendMargined() accept Control|Composite, unwrapping
 * Composite children just as the Box/Form/Grid/Group patches do.
 */
class Tab extends Generated\Tab
{
    /**
     * Append a page, unwrapping Composite if needed.
     *
     * @param string           $name   The tab label.
     * @param Control|Composite $child The page content.
     * @return $this
     */
    public function append(string $name, Control|Composite $child): static
    {
        $control = $child instanceof Composite ? $child->root() : $child;
        return parent::append($name, $control);
    }

    /**
     * Append a page and mark it margined in one step.
     *
     * @param string           $name The tab label.
     * @param Control|Composite $child The page content.
     * @return $this
     */
    public function appendMargined(string $name, Control|Composite $child): static
    {
        $this->append($name, $child);
        $this->setMargined($this->numPages() - 1, true);
        return $this;
    }

    /**
     * Append an ordered map of pages, keyed by their label.
     *
     * @param array<string, Control|Composite> $named Ordered map of title => content.
     * @return $this
     */
    public function pages(array $named): static
    {
        foreach ($named as $name => $child) {
            $this->append($name, $child);
        }
        return $this;
    }
}
