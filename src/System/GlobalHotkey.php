<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Libui\Ffi;
use Libui\Loop;

/**
 * Global (system-wide) hotkey support.
 *
 * Registers keyboard shortcuts that work even when the application window
 * is minimized or in the background.
 *
 * ## Basic usage
 *
 * ```php
 * $hk = new GlobalHotkey();
 *
 * // Register hotkeys
 * $hk->register('Cmd+Shift+A', function () {
 *     echo "Cmd+Shift+A pressed!\n";
 * });
 *
 * $hk->register('Ctrl+Alt+F1', function () {
 *     echo "Ctrl+Alt+F1 pressed!\n";
 * });
 *
 * // Start polling (must be called from libui event loop or a timer)
 * $hk->startPolling();
 *
 * // Later...
 * $hk->unregisterAll();
 * ```
 *
 * ## How it works
 *
 * 1. macOS Carbon `RegisterEventHotKey` registers system-wide hotkeys.
 * 2. When a hotkey is pressed, the Carbon handler stores the hotkey ID
 *    in a shared atomic variable.
 * 3. `poll()` checks the variable and fires the corresponding PHP callback.
 * 4. Call `poll()` from a libui timer or your event loop idle handler.
 *
 * ## Supported key syntax
 *
 * | Example | Meaning |
 * |---------|---------|
 * | Cmd+A | Command + A |
 * | Cmd+Shift+A | Command + Shift + A |
 * | Ctrl+Alt+F1 | Control + Option + F1 |
 * | Cmd+Shift+Esc | Command + Shift + Escape |
 * | Ctrl+Space | Control + Space |
 *
 * Modifiers: Cmd, Command, Shift, Alt, Option, Ctrl, Control
 * Keys: A-Z, 0-9, F1-F12, Space, Return, Enter, Escape, Esc,
 *       Tab, Delete, Backspace, Up, Down, Left, Right,
 *       Home, End, PageUp, PageDown
 */
class GlobalHotkey
{
    private static ?\FFI $ffi = null;

    /** @var array<int, callable> Registered hotkey id → callback */
    private array $callbacks = [];

    /** @var array<int, string> Registered hotkey id → description */
    private array $descriptions = [];

    /** @var int Monotonic hotkey ID counter */
    private int $nextId = 1;

    /** @var ?int Timer ID for polling (if polling was started) */
    private ?int $timerId = null;

    /** @var bool Whether FFI is initialized */
    private static bool $initialized = false;

    /**
     * Register a global hotkey.
     *
     * @param string   $keyCombo   Key combination, e.g. "Cmd+Shift+A"
     * @param callable $callback   Called when the hotkey is pressed.
     *                             Signature: `fn(int $hotkeyId, string $keyCombo): void`
     * @return int                 Hotkey ID (can be used to unregister)
     * @throws \RuntimeException   On registration failure
     */
    public function register(string $keyCombo, callable $callback): int
    {
        $ffi = self::ffi();
        $id = $this->nextId++;

        $result = $ffi->hotkey_register($keyCombo, $id);
        if ($result === 0) {
            throw new \RuntimeException(
                "Failed to register global hotkey: {$keyCombo}. "
                . 'Check that the key combination is valid.'
            );
        }

        $this->callbacks[$id] = $callback;
        $this->descriptions[$id] = $keyCombo;

        return $id;
    }

    /**
     * Unregister a previously registered hotkey.
     */
    public function unregister(int $id): void
    {
        $ffi = self::ffi();
        $ffi->hotkey_unregister($id);
        unset($this->callbacks[$id], $this->descriptions[$id]);
    }

    /**
     * Unregister all hotkeys.
     */
    public function unregisterAll(): void
    {
        $ffi = self::ffi();
        $ffi->hotkey_unregister_all();
        $this->callbacks = [];
        $this->descriptions = [];
    }

    /**
     * Poll for pressed hotkeys and fire callbacks.
     *
     * Call this periodically from a libui timer or your event loop tick:
     *
     * ```php
     * // In a libui timer callback (runs every 100ms):
     * $hk->poll();
     * ```
     *
     * Returns the number of callbacks fired.
     */
    public function poll(): int
    {
        $ffi = self::ffi();
        $fired = 0;

        while (true) {
            $id = $ffi->hotkey_poll();
            if ($id === 0) {
                break; // No more pending hotkeys
            }

            if (isset($this->callbacks[$id])) {
                ($this->callbacks[$id])($id, $this->descriptions[$id] ?? '');
                $fired++;
            }
        }

        return $fired;
    }

    /**
     * Start automatic polling via a libui repeat timer.
     *
     * Uses {@see Loop::repeat()} to call poll() every $intervalMs
     * milliseconds. The timer is automatically cancelled on destruction.
     *
     * @param int $intervalMs Polling interval in milliseconds (default: 100)
     */
    public function startPolling(int $intervalMs = 100): void
    {
        if ($this->timerId !== null) {
            return; // Already polling
        }

        $this->timerId = Loop::repeat($intervalMs, function () {
            $this->poll();
            return true; // Keep timer alive
        });
    }

    /**
     * Stop automatic polling.
     */
    public function stopPolling(): void
    {
        if ($this->timerId !== null) {
            Loop::cancel($this->timerId);
            $this->timerId = null;
        }
    }

    /**
     * Clean up on destruction.
     */
    public function __destruct()
    {
        $this->stopPolling();
        $this->unregisterAll();
    }

    /**
     * Get or initialize the hotkey FFI.
     */
    private static function ffi(): \FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $base = \dirname(__DIR__, 2) . '/bridge';
        $libPath = match (\PHP_OS_FAMILY) {
            'Darwin'  => $base . '/hotkey.dylib',
            'Linux'   => $base . '/libhotkey.so',
            'Windows' => $base . '/hotkey.dll',
            default   => throw new \RuntimeException('Unsupported OS: ' . \PHP_OS_FAMILY),
        };

        if (!\file_exists($libPath)) {
            throw new \RuntimeException(
                'Hotkey bridge not found at: ' . $libPath . \PHP_EOL
                . 'Compile instructions:' . \PHP_EOL
                . '  macOS:   cd bridge && clang -shared -fobjc-arc hotkey.m'
                . ' -framework Carbon -framework AppKit -o hotkey.dylib' . \PHP_EOL
                . '  Linux:   cd bridge && gcc -shared -fPIC hotkey_linux.c'
                . ' $(pkg-config --cflags --libs x11) -o libhotkey.so' . \PHP_EOL
                . '  Windows: cd bridge && cl /LD hotkey_win.c /Fe:hotkey.dll user32.lib'
            );
        }

        self::$ffi = \FFI::cdef(
            'int hotkey_register(const char *key_combo, int id);'
            . 'void hotkey_unregister(int id);'
            . 'void hotkey_unregister_all(void);'
            . 'int hotkey_poll(void);',
            $libPath,
        );

        return self::$ffi;
    }
}