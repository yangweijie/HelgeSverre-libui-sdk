<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Thrown when a required capability is unavailable.
 */
final class CapabilityException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
