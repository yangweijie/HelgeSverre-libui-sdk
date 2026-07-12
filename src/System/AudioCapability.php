<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Capability check for the Audio system class.
 *
 * Verifies that the audio bridge library exists for the current platform.
 * Does NOT attempt audio engine initialisation — that happens lazily in
 * Audio::init() / Audio::load().
 */
final class AudioCapability implements Capability
{
    public function name(): string
    {
        return 'audio';
    }

    public function available(): bool
    {
        return $this->bridgePath() !== null;
    }

    public function reason(): ?string
    {
        return $this->available() ? null : 'Audio bridge library not found.';
    }

    public function dependencies(): array
    {
        if ($this->bridgePath() !== null) {
            return [];
        }

        $deps = [];
        $base = \dirname(__DIR__, 2) . '/bridge';
        $libName = match (\PHP_OS_FAMILY) {
            'Darwin'  => 'audio.dylib',
            'Linux'   => 'libaudio.so',
            'Windows' => 'audio.dll',
            default   => null,
        };

        if ($libName === null) {
            $deps[] = 'Unsupported OS: ' . \PHP_OS_FAMILY;
        } else {
            $path = $base . '/' . $libName;
            if (!\file_exists($path)) {
                $deps[] = "{$path} not found — compile the audio bridge first";
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
            'Darwin'  => \is_file($base . '/audio.dylib') ? $base . '/audio.dylib' : null,
            'Linux'   => \is_file($base . '/libaudio.so') ? $base . '/libaudio.so' : null,
            'Windows' => \is_file($base . '/audio.dll')   ? $base . '/audio.dll'   : null,
            default   => null,
        };
    }
}
