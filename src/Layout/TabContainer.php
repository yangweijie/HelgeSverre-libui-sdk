<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Layout;

use Libui\Control;
use Libui\Tab;
use Yangweijie\Ui2\Composite;

/**
 * A convenience wrapper around {@see Tab} that tracks page labels,
 * accepts {@see Composite} children directly, and supports dynamic
 * page management.
 *
 * The upstream Tab patch already accepts Composite via append()/
 * appendMargined(); this wrapper adds label tracking and a helper
 * to re-read the current page list.
 *
 * ```php
 * $tabs = new TabContainer();
 * $tabs->addPage('General', new Label('Content'), true);
 * $tabs->addPage('Advanced', $settingsForm);
 * ```
 */
class TabContainer extends Composite
{
    private Tab $tab;

    /** @var list<array{label: string, margined: bool}> */
    private array $pages = [];

    public function __construct()
    {
        $this->tab = new Tab();
    }

    /**
     * Add a page to the tab container.
     *
     * @param string          $label    The tab label.
     * @param Control|Composite $child   The page content.
     * @param bool            $margined Whether to enable margins on this page.
     */
    public function addPage(string $label, Control|Composite $child, bool $margined = false): static
    {
        $this->tab->append($label, $child, $margined);
        $this->pages[] = ['label' => $label, 'margined' => $margined];
        return $this;
    }

    /**
     * Remove a page by index.
     */
    public function removePage(int $index): static
    {
        $this->tab->delete($index);
        array_splice($this->pages, $index, 1);
        return $this;
    }

    /**
     * Number of pages currently in the container.
     */
    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * Get the page labels in order.
     *
     * @return list<string>
     */
    public function pageLabels(): array
    {
        return array_map(static fn (array $p): string => $p['label'], $this->pages);
    }

    /**
     * The underlying upstream Tab for direct access.
     */
    public function tab(): Tab
    {
        return $this->tab;
    }

    public function root(): Control
    {
        return $this->tab;
    }
}
