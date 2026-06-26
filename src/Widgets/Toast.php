<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use RuntimeException;

/**
 * Send native OS desktop toast notifications via the PebView Toast.dylib FFI.
 *
 * ```php
 * Toast::show('Hello', 'This is a notification');
 * Toast::show('Alert', 'With icon', '/path/to/icon.png');
 * ```
 */
class Toast
{
    private static ?\FFI $ffi = null;
    private static ?string $lastError = null;

    /**
     * Show a native desktop toast notification.
     *
     * @param string      $title   Notification title
     * @param string      $message Notification body
     * @param string|null $icon    Optional icon path (null = no icon)
     * @return bool True if the notification was shown successfully
     *
     * @throws RuntimeException If the Toast dylib cannot be loaded
     */
    public static function show(string $title, string $message, ?string $icon = null): bool
    {
        try {
            $ffi = self::ffi();
            $result = $ffi->toastShow('ui2', $title, $message, $icon ?? '');
            if (!$result) {
                self::$lastError = 'toastShow returned false';
                return false;
            }
            self::$lastError = null;
            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Get or initialise the FFI instance for Toast.dylib.
     *
     * @throws RuntimeException If the library cannot be loaded
     */
    private static function ffi(): \FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $libPath = self::libraryPath();

        if (!file_exists($libPath)) {
            throw new RuntimeException("Toast library not found at: {$libPath}");
        }

        self::$ffi = \FFI::cdef(
            'bool toastShow(const char *app, const char *title, const char *message, const char *image_path);',
            $libPath,
        );

        return self::$ffi;
    }

    /**
     * Resolve the platform-specific library path.
     */
    private static function libraryPath(): string
    {
        $base = dirname(__DIR__, 2) . '/vendor/kingbes/pebview/lib';

        return match (PHP_OS_FAMILY) {
            'Darwin'  => $base . '/macos/arm64/Toast.dylib',
            'Linux'   => $base . '/linux/x86_64/libToast.so',
            'Windows' => $base . '/windows/x86_64/Toast.dll',
            default   => throw new RuntimeException('Unsupported platform for Toast: ' . PHP_OS_FAMILY),
        };
    }
}
