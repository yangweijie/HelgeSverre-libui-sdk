<?php

declare(strict_types=1);

namespace Libui\Exception;

/**
 * Thrown when a Menu is created after a Window already exists.
 *
 * libui requires every menu to be built BEFORE the first window; violating this
 * silently breaks the menu bar (and can crash). This is a programmer error, so it
 * extends LogicException.
 *
 * PATCHED: now carries the title of the first Window that was created, making
 * it easier to locate the offending code during debugging.
 */
final class MenuOrderException extends \LogicException
{
    /** The title of the first Window that locked the menu system, if available. */
    private ?string $windowTitle = null;

    public function __construct(
        string $message = '',
        ?string $windowTitle = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->windowTitle = $windowTitle;
        parent::__construct($message, $code, $previous);
    }

    /**
     * The title of the first Window that was created, or null if unavailable
     * (e.g. when the exception is thrown without Window context).
     */
    public function getWindowTitle(): ?string
    {
        return $this->windowTitle;
    }
}
