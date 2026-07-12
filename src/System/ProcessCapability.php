<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the ProcessUtil class.
 *
 * ProcessUtil is pure PHP (wraps illuminate/process → Symfony Process) and
 * is always available on any platform that supports PHP 8.5+ with the
 * required composer package installed.  There is nothing platform-specific
 * about running sub-processes.
 */
final class ProcessCapability implements Capability
{
    public function name(): string
    {
        return 'process';
    }

    public function available(): bool
    {
        return \class_exists(\Illuminate\Process\Factory::class);
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'illuminate/process package not installed.';
    }

    public function dependencies(): array
    {
        if ($this->available()) {
            return [];
        }

        $deps = [];
        $deps[] = 'Run `composer require illuminate/process`';
        return $deps;
    }
}
