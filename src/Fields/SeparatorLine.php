<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Control;
use Libui\Separator;
use Yangweijie\Ui2\Composite;

/**
 * A horizontal separator line, wrapping the upstream Separator widget.
 *
 * Useful as a visual divider between sections in a Form or Box layout.
 *
 * ```php
 * $box->append(new SeparatorLine());
 * ```
 */
class SeparatorLine extends Composite
{
    private readonly Separator $separator;

    public function __construct()
    {
        $this->separator = new Separator();
    }

    /**
     * No explicit destructor: a separator is always a child of the Box it is
     * appended to, and libui frees child controls when their parent is destroyed.
     * Calling destroy() here would fire libui's "cannot destroy a control that
     * still has a parent" assertion when the PHP wrapper is garbage-collected
     * (see Control::__destruct, which only frees toplevel controls).
     */
    public function root(): Control
    {
        return $this->separator;
    }
}
