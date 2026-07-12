<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the Tray system class.
 *
 * Verifies that the PebView native library is present for the current
 * platform. The tray feature cannot work without it, and there is no
 * pure-PHP fallback — system tray integration is inherently platform-specific
 * native code.
 */
final class TrayCapability implements Capability
{
    public function name(): string
    {
        return 'tray';
    }

    public function available(): bool
    {
        return $this->pebviewPath() !== null;
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'PebView library not found. Run `composer build:pebview` first.';
    }

    public function dependencies(): array
    {
        if ($this->pebviewPath() !== null) {
            return [];
        }

        $deps = [];
        $base = \dirname(__DIR__, 2) . '/vendor/kingbes/pebview/lib';
        $libName = match (\PHP_OS_FAMILY) {
            'Darwin'  => 'macos/arm64/PebView.dylib',
            'Linux'   => 'linux/x86_64/libPebView.so',
            'Windows' => 'windows/PebView.dll',
            default   => null,
        };

        if ($libName === null) {
            $deps[] = 'Unsupported OS: ' . \PHP_OS_FAMILY;
        } else {
            $path = $base . '/' . $libName;
            if (!\file_exists($path)) {
                $deps[] = "{$path} not found — run `composer build:pebview`";
            }
            if (!\extension_loaded('ffi')) {
                $deps[] = 'PHP extension ffi is not loaded';
            }
        }
        return $deps;
    }

    /** @return non-empty-string|null */
    private function pebviewPath(): ?string
    {
        $base = \dirname(__DIR__, 2) . '/vendor/kingbes/pebview/lib';
        return match (\PHP_OS_FAMILY) {
            'Darwin'  => \is_file($base . '/macos/arm64/PebView.dylib') ? $base . '/macos/arm64/PebView.dylib' : null,
            'Linux'   => \is_file($base . '/linux/x86_64/libPebView.so') ? $base . '/linux/x86_64/libPebView.so' : null,
            'Windows' => \is_file($base . '/windows/PebView.dll') ? $base . '/windows/PebView.dll' : null,
            default   => null,
        };
    }
}
