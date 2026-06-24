<?php

declare(strict_types=1);

namespace Libui;

use Yangweijie\Ui2\Composite;

/**
 * Stacks children vertically (default) or horizontally. Adds a padded
 * constructor option and a readable stretchy append on top of the generated API.
 */
class Box extends Generated\Box
{
    public function __construct(bool $padded = false)
    {
        parent::__construct();
        if ($padded) {
            $this->setPadded(true);
        }
    }

    public static function horizontal(bool $padded = false): static
    {
        $box = parent::horizontal();
        if ($padded) {
            $box->setPadded(true);
        }
        return $box;
    }

    /** Append a child (Control or Composite); $stretchy defaults to non-stretching. */
    public function append(Control|Composite $child, bool|int $stretchy = false): static
    {
        $control = $child instanceof Composite ? $child->root() : $child;
        return parent::append($control, (int) $stretchy);
    }

    /** Append a child (Control or Composite) that grows to fill the box's main axis. */
    public function appendStretchy(Control|Composite $child): static
    {
        return $this->append($child, true);
    }
}
