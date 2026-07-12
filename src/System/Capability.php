<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

/**
 * Describes a runtime capability the System namespace offers.
 *
 * Each capability can be independently checked for availability before
 * constructing the underlying System class.  This lets you fail early with
 * a clear, actionable message instead of catching a cryptic FFI error at
 * runtime.
 *
 * ```php
 * $audio = CapabilityRegistry::require('audio');
 * $sound = Audio::load('sound.mp3');        // safe to call now
 * ```
 */
interface Capability
{
    /** Machine-readable capability name (e.g. 'audio', 'tray'). */
    public function name(): string;

    /** Whether the capability is usable on this platform right now. */
    public function available(): bool;

    /**
     * Human-readable reason why the capability is unavailable.
     * Returns null when {@see available()} is true.
     */
    public function reason(): ?string;

    /**
     * List of missing dependencies, if any.
     *
     * @return list<string> e.g. ['bridge/audio.dylib not found', 'extension ffi missing']
     */
    public function dependencies(): array;
}
