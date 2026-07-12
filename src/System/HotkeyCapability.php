<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the GlobalHotkey system class.
 *
 * Verifies that the hotkey bridge library exists for the current platform.
 * Global hotkeys require platform-specific native code (Carbon Event HotKey
 * on macOS, X11 on Linux, user32 on Windows), so there is no pure-PHP fallback
 * for this feature.
 */
final class HotkeyCapability implements Capability
{
    public function name(): string
    {
        return 'hotkey';
    }

    public function available(): bool
    {
        return $this->bridgePath() !== null;
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'Hotkey bridge library not found.';
    }

    public function dependencies(): array
    {
        if ($this->bridgePath() !== null) {
            return [];
        }

        $deps = [];
        $base = \dirname(__DIR__, 2) . '/bridge';
        $libName = match (\PHP_OS_FAMILY) {
            'Darwin'  => 'hotkey.dylib',
            'Linux'   => 'libhotkey.so',
            'Windows' => 'hotkey.dll',
            default   => null,
        };

        if ($libName === null) {
            $deps[] = 'Unsupported OS: ' . \PHP_OS_FAMILY;
        } else {
            $path = $base . '/' . $libName;
            if (!\file_exists($path)) {
                $deps[] = "{$path} not found — compile the hotkey bridge first";
            }
            if (!\extension_loaded('ffi')) {
                $deps[] = 'PHP extension ffi is not loaded';
            }
        }
        return $deps;
    }

    /** @return non-empty-string|null */
    private function bridgePath(): ?string
    {
        $base = \dirname(__DIR__, 2) . '/bridge';
        return match (\PHP_OS_FAMILY) {
            'Darwin'  => \is_file($base . '/hotkey.dylib') ? $base . '/hotkey.dylib' : null,
            'Linux'   => \is_file($base . '/libhotkey.so') ? $base . '/libhotkey.so' : null,
            'Windows' => \is_file($base . '/hotkey.dll')   ? $base . '/hotkey.dll'   : null,
            default   => null,
        };
    }
}
