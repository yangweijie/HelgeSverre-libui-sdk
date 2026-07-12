<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the SystemInfo class.
 *
 * SystemInfo is pure PHP (wraps utopia-php/system) and is always available
 * on any platform that supports PHP 8.5+.  Individual features may still
 * report null / N/A on platforms that do not support them (e.g. Windows
 * missing getCPUCores()), but the capability itself is universally present.
 */
final class SystemInfoCapability implements Capability
{
    public function name(): string
    {
        return 'systeminfo';
    }

    public function available(): bool
    {
        return \class_exists(\Utopia\System\System::class);
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'utopia-php/system package not installed.';
    }

    public function dependencies(): array
    {
        if ($this->available()) {
            return [];
        }

        $deps = [];
        $composerPath = \dirname(__DIR__, 2) . '/composer.json';
        if (!\is_file($composerPath)) {
            $deps[] = 'Not a composer project (composer.json not found)';
        } else {
            $deps[] = 'Run `composer require utopia-php/system`';
        }
        return $deps;
    }
}
