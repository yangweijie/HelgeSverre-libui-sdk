<?php

declare(strict_types=1);

namespace Yangweijie\Ui2;

use Libui\Control;
use Libui\HasValue;

/**
 * Abstract base for composite widgets — UI components built from multiple
 * controls that act as a single unit.
 *
 * A Composite wraps one or more child controls behind a single ::root() control
 * so the whole group can be added to containers as if it were a single widget:
 *
 *     $layout->append(new SearchField('Type a name...'));
 *
 * Subclasses that expose a value implement {@see HasValue} via the abstract's
 * default value()/setValue() stubs — override to forward to the inner widget:
 *
 *     class SearchField extends Composite
 *     {
 *         public function __construct(string $placeholder = '') { … }
 *         public function root(): Control { return $this->box; }
 *         public function value(): mixed { return $this->entry->text(); }
 *     }
 */
abstract class Composite implements HasValue
{
    /**
     * The top-level control that represents this composite when added to a
     * container (Box, Form, Grid, etc.).
     */
    abstract public function root(): Control;

    /**
     * Returns the composite's current value, or null if it has no single value.
     *
     * Override in subclasses with a real value to implement {@see HasValue}.
     */
    public function value(): mixed
    {
        return null;
    }

    /**
     * Sets the composite's value.
     *
     * Override in subclasses to propagate the value to the inner widget.
     *
     * @return $this
     */
    public function setValue(mixed $value): static
    {
        return $this;
    }

    /**
     * Upcast the root control to a native uiControl pointer so containers
     * (Box, Form, Grid, …) can accept this composite natively.
     *
     * Containers call this internally when they see a Composite — you do not
     * need to call it yourself.
     */
    public function asControl(): \FFI\CData
    {
        return $this->root()->asControl();
    }
}
