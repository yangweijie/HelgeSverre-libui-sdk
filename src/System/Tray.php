<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\System;

use Libui\Window;
use Libui\Ffi;

/**
 * System tray icon with context menu — wraps PebView's native tray capability.
 *
 * Creates an NSStatusItem (macOS) / GtkStatusIcon (Linux) / system tray icon
 * (Windows) with a popup context menu.  Each menu item can have a click callback.
 *
 * ```php
 * $tray = new Tray($window, '/path/to/icon.png');
 * $tray->addItem('Show Window', function () use ($window) { $window->show(); })
 *      ->addSeparator()
 *      ->addItem('Quit', function () use ($app) { $app->quit(); })
 *      ->attach();
 * ```
 */
class Tray
{
    private static ?\FFI $ffi = null;

    /** @var \FFI\CData|null Tray handle returned by window_tray() */
    private ?\FFI\CData $trayHandle = null;

    /** @var \FFI\CData|null Native window pointer */
    private ?\FFI\CData $winPtr = null;

    /** @var list<\FFI\CData> Retained tray_menu structs (FFI keeps them alive) */
    private array $menuStructs = [];

    /** @var list<callable> Retained PHP callbacks (keeps FFI trampolines alive) */
    private array $callbacks = [];

    /** @var int Monotonic menu item ID counter */
    private int $nextId = 0;

    private string $iconPath;

    /** @var list<array{text:string, callback:?callable, disabled:bool, checked:bool}> Buffered items before attach() */
    private array $pendingItems = [];

    /** @var bool Whether attach() has been called */
    private bool $attached = false;

    /**
     * @param Window $window  The libui Window to attach the tray to.
     * @param string $iconPath  Path to the tray icon file
     *        (macOS: .png recommended; Linux: .png; Windows: .ico).
     */
    public function __construct(
        private Window $window,
        string $iconPath,
    ) {
        $this->iconPath = $iconPath;
    }

    /**
     * Create the tray icon.
     *
     * Must be called before the app event loop starts (before run()).
     * Returns $this for chaining.
     *
     * @throws \RuntimeException On FFI or platform error.
     */
    public function attach(): static
    {
        if ($this->trayHandle !== null) {
            return $this; // Already attached
        }

        $ffi = self::ffi();

        // Get the native NSWindow/NSView handle from the libui Window
        $winHandle = $this->window->handle();

        // Cast to void* via FFI instance (avoids deprecated static calls)
        $ptrType = $ffi->type('void*');
        $this->winPtr = $ffi->cast($ptrType, $winHandle);

        // Allocate and copy icon path as a C string
        $iconNative = $ffi->new('char[' . (\strlen($this->iconPath) + 1) . ']');
        \FFI::memcpy($iconNative, $this->iconPath, \strlen($this->iconPath));

        $this->trayHandle = $ffi->window_tray(
            $ffi->cast($ptrType, \FFI::addr($winHandle)),
            $iconNative,
        );

        if (\FFI::isNull($this->trayHandle)) {
            throw new \RuntimeException(
                'Failed to create tray icon. Check that the icon file exists: ' . $this->iconPath,
            );
        }

        // Flush any items buffered before attach()
        foreach ($this->pendingItems as $item) {
            $this->addItemInternal(
                $item['text'],
                $item['callback'],
                $item['disabled'],
                $item['checked'],
            );
        }
        $this->pendingItems = [];
        $this->attached = true;

        return $this;
    }

    /**
     * Add a menu item to the tray context menu.
     *
     * @param string   $text     Menu item label. Use '-' for a separator.
     * @param callable $callback Function to call when item is clicked.
     *                           Receives no arguments (void callback in PebView).
     * @param bool     $disabled Whether the item is greyed out (default: false).
     * @param bool     $checked  Whether the item shows a checkmark (default: false).
     * @return $this
     */
    public function addItem(
        string $text,
        ?callable $callback = null,
        bool $disabled = false,
        bool $checked = false,
    ): static {
        // Not yet attached — buffer the item for later submission
        if ($this->trayHandle === null) {
            $this->pendingItems[] = [
                'text'     => $text,
                'callback' => $callback,
                'disabled' => $disabled,
                'checked'  => $checked,
            ];
            return $this;
        }

        return $this->addItemInternal($text, $callback, $disabled, $checked);
    }

    /**
     * Internal: actually add an item to the native tray (requires trayHandle).
     */
    private function addItemInternal(
        string $text,
        ?callable $callback = null,
        bool $disabled = false,
        bool $checked = false,
    ): static {

        $ffi = self::ffi();
        $id = $this->nextId++;

        // Create the tray_menu struct
        $menuType = $ffi->type('struct tray_menu');
        $menu = $ffi->new($menuType);
        $menu->id = $id;

        // Text: allocate a C string
        $textLen = \strlen($text);
        $textBuf = $ffi->new("char[{$textLen} + 1]");
        \FFI::memcpy($textBuf, $text, $textLen);
        $textBuf[$textLen] = "\0";
        $menu->text = $ffi->cast('char*', \FFI::addr($textBuf));

        $menu->disabled = $disabled ? 1 : 0;
        $menu->checked = $checked ? 1 : 0;

        // Callback: wrap the PHP callable into a C function pointer.
        if ($callback !== null && $text !== '-') {
            $cbType = $ffi->type('void(*)(const void*)');
            $cbCData = $ffi->new($cbType);

            $cbCData = function ($ptr) use ($callback): void {
                $callback();
            };

            $menu->callback = $cbCData;
        }

        // Add the menu via FFI
        $ffi->window_tray_add_menu($this->trayHandle, \FFI::addr($menu));

        // Retain structs and callables so FFI trampolines stay alive
        $this->menuStructs[] = $menu;
        $this->callbacks[] = $cbCData ?? null;
        if (isset($textBuf)) {
            $this->menuStructs[] = $textBuf;
        }

        return $this;
    }

    /**
     * Convenience: add a separator line.
     */
    public function addSeparator(): static
    {
        return $this->addItem('-');
    }

    /**
     * Remove the tray icon from the system tray.
     */
    public function remove(): void
    {
        if ($this->trayHandle === null) {
            return;
        }
        $ffi = self::ffi();
        $ffi->window_tray_remove($this->trayHandle);
        $this->trayHandle = null;
        $this->menuStructs = [];
        $this->callbacks = [];
    }

    /**
     * Clean up on destruction.
     */
    public function __destruct()
    {
        $this->remove();
    }

    /**
     * Get or initialise the PebView FFI instance with tray functions.
     */
    private static function ffi(): \FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $base = \dirname(__DIR__, 2) . '/vendor/kingbes/pebview/lib';

        $libPath = match (\PHP_OS_FAMILY) {
            'Darwin'  => $base . '/macos/arm64/PebView.dylib',
            'Linux'   => $base . '/linux/x86_64/libPebView.so',
            'Windows' => $base . '/windows/x86_64/PebView.dll',
            default   => throw new \RuntimeException('Unsupported platform: ' . \PHP_OS_FAMILY),
        };

        if (!\file_exists($libPath)) {
            throw new \RuntimeException("PebView library not found at: {$libPath}");
        }

        self::$ffi = \FFI::cdef(
            'void *window_tray(const void *ptr, const char *icon);'
            . 'void window_tray_add_menu(const void *tray, struct tray_menu *menu);'
            . 'void window_tray_remove(void *tray);'
            . 'struct tray_menu { int id; char *text; int disabled; int checked; void (*callback)(const void *ptr); };',
            $libPath,
        );

        return self::$ffi;
    }
}
