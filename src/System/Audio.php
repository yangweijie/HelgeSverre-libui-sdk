<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Libui\Ffi;
use Libui\Loop;

/**
 * Cross-platform audio playback backed by the miniaudio bridge
 * (bridge/audio.{dylib,so,dll}).
 *
 * The bridge is a thin FFI wrapper over miniaudio's high-level engine. Sounds
 * are referenced by opaque 1-based integer handles — the raw ma_sound* is never
 * exposed to PHP, which keeps the FFI surface safe.
 *
 * Supported operations: load / play / pause / resume / stop / setVolume /
 * setLooping / isPlaying / onEnded.
 *
 * The onEnded callback is delivered via a periodic poll (Loop::repeat) that
 * detects the playing→stopped transition. We avoid wiring a C→PHP closure into
 * miniaudio's end callback, which is fragile and leaks under FFI.
 *
 * Example:
 * ```php
 * Audio::init();
 * $sound = Audio::load('/path/to/sound.mp3')
 *     ->setVolume(0.8)
 *     ->setLooping(false)
 *     ->onEnded(fn () => print "finished\n")
 *     ->play();
 * ```
 */
final class Audio
{
    private static ?\FFI $ffi = null;
    private static bool $engineInitialized = false;

    private int $handle = 0;
    private bool $looping = false;
    private ?\Closure $endCallback = null;
    private ?int $timerId = null;
    private bool $wasPlaying = false;

    /* ------------------------------ engine ------------------------------ */

    /**
     * Initialize the miniaudio engine. Idempotent. Call once before loading
     * sounds (load() also lazily initializes if you skip this).
     *
     * @throws \RuntimeException if the engine cannot start (no audio device / backend).
     */
    public static function init(): void
    {
        $ffi = self::ffi();
        if ($ffi->audio_init() !== 1) {
            throw new \RuntimeException('Failed to initialize audio engine (miniaudio backend unavailable).');
        }
        self::$engineInitialized = true;
    }

    /**
     * Shut down the engine and free every loaded sound. Call after all
     * Audio instances have been unloaded / destroyed.
     */
    public static function shutdown(): void
    {
        if (self::$ffi === null) {
            return;
        }
        self::$ffi->audio_shutdown();
        self::$engineInitialized = false;
    }

    /* ----------------------------- loading ------------------------------ */

    /**
     * Load an audio file (mp3 / wav / flac / ogg, depending on miniaudio build).
     *
     * @throws \RuntimeException if the file is missing or cannot be decoded.
     */
    public static function load(string $path): self
    {
        if (!\file_exists($path)) {
            throw new \RuntimeException("Audio file not found: {$path}");
        }
        if (!self::$engineInitialized) {
            self::init();
        }

        $handle = self::ffi()->audio_load($path);
        if ($handle <= 0) {
            throw new \RuntimeException("Failed to load audio file: {$path}");
        }

        return new self($handle);
    }

    private function __construct(int $handle)
    {
        $this->handle = $handle;
    }

    /* --------------------------- playback API --------------------------- */

    public function play(): self
    {
        self::ffi()->audio_play($this->handle, $this->looping ? 1 : 0);
        $this->startPolling();
        return $this;
    }

    public function pause(): self
    {
        self::ffi()->audio_pause($this->handle);
        $this->stopPolling();
        return $this;
    }

    public function resume(): self
    {
        self::ffi()->audio_resume($this->handle);
        $this->startPolling();
        return $this;
    }

    public function stop(): self
    {
        self::ffi()->audio_stop($this->handle);
        $this->stopPolling();
        return $this;
    }

    public function setVolume(float $volume): self
    {
        self::ffi()->audio_set_volume($this->handle, $volume);
        return $this;
    }

    public function setLooping(bool $loop): self
    {
        $this->looping = $loop;
        self::ffi()->audio_set_looping($this->handle, $loop ? 1 : 0);
        return $this;
    }

    public function isPlaying(): bool
    {
        return self::ffi()->audio_is_playing($this->handle) === 1;
    }

    public function isLooping(): bool
    {
        return $this->looping;
    }

    public function getHandle(): int
    {
        return $this->handle;
    }

    /**
     * Register a callback fired when playback ends naturally (not on
     * pause/stop). Returns $this for chaining.
     */
    public function onEnded(callable $callback): self
    {
        $this->endCallback = \Closure::fromCallable($callback);
        return $this;
    }

    public function unload(): void
    {
        $this->stopPolling();
        if (self::$ffi !== null && self::$engineInitialized && $this->handle !== 0) {
            self::$ffi->audio_unload($this->handle);
        }
        $this->handle = 0;
    }

    public function __destruct()
    {
        $this->unload();
    }

    /* ------------------------- onEnded polling -------------------------- */

    private function startPolling(): void
    {
        if ($this->timerId !== null) {
            return;
        }
        // onEnded polling rides on the libui event loop. If it is not running
        // (e.g. pure CLI context with no GUI), skip gracefully — playback
        // still works via the C bridge, only the end callback is unavailable.
        if (!\class_exists(Ffi::class) || !Ffi::isInitialized()) {
            return;
        }
        $this->wasPlaying = $this->isPlaying();
        $this->timerId = Loop::repeat(100, function (): void {
            $now = $this->isPlaying();
            if ($this->wasPlaying && !$now) {
                // natural playing -> stopped transition
                $this->wasPlaying = false;
                if ($this->endCallback !== null) {
                    ($this->endCallback)();
                }
                if (!$this->looping) {
                    $this->stopPolling();
                }
                return;
            }
            $this->wasPlaying = $now;
        });
    }

    private function stopPolling(): void
    {
        if ($this->timerId !== null) {
            try {
                Loop::cancel($this->timerId);
            } catch (\Throwable) {
                // Loop already torn down; ignore.
            }
            $this->timerId = null;
        }
    }

    /* ------------------------------- FFI -------------------------------- */

    private static function ffi(): \FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $base = \dirname(__DIR__, 2) . '/bridge';
        $libPath = match (\PHP_OS_FAMILY) {
            'Darwin'  => $base . '/audio.dylib',
            'Linux'   => $base . '/libaudio.so',
            'Windows' => $base . '/audio.dll',
            default   => throw new \RuntimeException('Unsupported OS: ' . \PHP_OS_FAMILY),
        };

        if (!\file_exists($libPath)) {
            throw new \RuntimeException(
                'Audio bridge not found at: ' . $libPath . \PHP_EOL
                . 'Compile instructions:' . \PHP_EOL
                . '  macOS:   cd bridge && clang -shared -fPIC -DMINIAUDIO_IMPLEMENTATION audio.c'
                . ' -framework CoreFoundation -framework AudioToolbox -framework AudioUnit -o audio.dylib' . \PHP_EOL
                . '  Linux:   cd bridge && gcc -shared -fPIC -DMINIAUDIO_IMPLEMENTATION audio.c -lasound -lpthread -o libaudio.so' . \PHP_EOL
                . '  Windows: cd bridge && cl /LD /DMINIAUDIO_IMPLEMENTATION audio.c /Fe:audio.dll'
            );
        }

        self::$ffi = \FFI::cdef(
            'int audio_init(void);'
            . 'void audio_shutdown(void);'
            . 'int audio_load(const char *path);'
            . 'void audio_unload(int handle);'
            . 'int audio_play(int handle, int loop);'
            . 'void audio_resume(int handle);'
            . 'void audio_pause(int handle);'
            . 'void audio_stop(int handle);'
            . 'void audio_set_volume(int handle, float volume);'
            . 'void audio_set_looping(int handle, int loop);'
            . 'int audio_is_playing(int handle);',
            $libPath,
        );

        return self::$ffi;
    }
}
