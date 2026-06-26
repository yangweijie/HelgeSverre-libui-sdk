<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Logging;

use Monolog\Handler\BufferHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Formatter\LineFormatter;

/**
 * Async/buffered logger for desktop GUI applications.
 *
 * Log entries are buffered in memory and flushed to disk asynchronously,
 * preventing file I/O from blocking the UI event loop.
 *
 * ## Basic usage
 *
 * ```php
 * use Yangweijie\Ui2\Logging\Log;
 *
 * Log::info('Application started', ['pid' => getmypid()]);
 * Log::debug('Drawing widget at x={x}, y={y}', ['x' => 30, 'y' => 50]);
 * Log::warning('Low memory', ['free' => $freeBytes]);
 * Log::error('Failed to open file: {path}', ['path' => $path]);
 *
 * // Flush buffer to disk explicitly (e.g. on window close / idle timer)
 * Log::flush();
 * ```
 *
 * ## Architecture
 *
 * ┌─────────┐   ┌───────────────┐   ┌──────────────┐   ┌──────────────┐
 * │ Log::info│──▶│ Monolog Logger│──▶│ BufferHandler│──▶│ StreamHandler│──▶ file
 * │ Log::err │   │ (PSR-3)       │   │ (async buffer)│   │ (flush to disk)│
 * └─────────┘   └───────────────┘   └──────────────┘   └──────────────┘
 *                                         │
 *                                         ▼
 *                                  flush() / shutdown
 *
 * The buffer is automatically flushed when:
 * 1. The PHP process shuts down (register_shutdown_function)
 * 2. You explicitly call Log::flush()
 * 3. Buffer exceeds flushThreshold entries
 */
class Log
{
    private static ?MonologLogger $logger = null;
    private static ?BufferHandler $buffer = null;
    private static bool $initialized = false;

    /**
     * Initialize the logger.
     *
     * @param string|null $logFile  Absolute path to the log file.
     *                              Default: sys_get_temp_dir() . '/ui2-YYYY-MM-DD.log'
     * @param int         $level    Minimum log level (MonologLogger::DEBUG, INFO, WARNING, ERROR)
     * @param int         $flushThreshold  Auto-flush after this many buffered entries (0 = never)
     */
    public static function init(
        ?string $logFile = null,
        int $level = MonologLogger::DEBUG,
        int $flushThreshold = 50,
    ): void {
        if (self::$initialized) {
            return;
        }

        $logFile ??= \sys_get_temp_dir() . '/ui2-' . \date('Y-m-d') . '.log';

        // Ensure log directory exists
        $dir = \dirname($logFile);
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0755, true);
        }

        // Stream handler writes to file
        $stream = new StreamHandler($logFile, $level);
        $stream->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d H:i:s.u',
        ));

        // Buffer handler — holds entries in memory until flush()
        self::$buffer = new BufferHandler(
            $stream,
            $flushThreshold,   // auto-flush after N entries (0 = never)
            MonologLogger::DEBUG,
            true,              // bubble
            true,              // flushOnOverflow — flush when buffer is full
        );

        self::$logger = new MonologLogger('ui2');
        self::$logger->pushHandler(self::$buffer);

        // Register shutdown handler — flushes remaining buffer on exit
        \register_shutdown_function(function () {
            self::flush();
        });

        self::$initialized = true;
    }

    /**
     * Log a DEBUG message.
     *
     * @param string $message  Log message with optional {placeholder}s
     * @param array  $context  Context data to merge into message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->debug($message, $context);
    }

    /**
     * Log an INFO message.
     */
    public static function info(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->info($message, $context);
    }

    /**
     * Log a NOTICE message.
     */
    public static function notice(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->notice($message, $context);
    }

    /**
     * Log a WARNING message.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->warning($message, $context);
    }

    /**
     * Log an ERROR message.
     */
    public static function error(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->error($message, $context);
    }

    /**
     * Log a CRITICAL message.
     */
    public static function critical(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->critical($message, $context);
    }

    /**
     * Log an ALERT message.
     */
    public static function alert(string $message, array $context = []): void
    {
        self::ensureInit();
        self::$logger->alert($message, $context);
    }

    /**
     * Flush buffered log entries to disk.
     *
     * Call this from a UI idle timer or when you know it's safe to do I/O:
     *
     * ```php
     * // Inside an AreaDelegate or Window callback, after heavy work:
     * Log::flush();
     * ```
     *
     * It is also automatically called on PHP shutdown.
     */
    public static function flush(): void
    {
        if (self::$buffer !== null) {
            self::$buffer->flush();
        }
    }

    /**
     * Get the underlying Monolog logger instance (e.g. for PSR-3 type-hinting).
     */
    public static function getLogger(): ?MonologLogger
    {
        return self::$logger;
    }

    /**
     * Reset the logger (mostly for testing).
     */
    public static function reset(): void
    {
        self::flush();
        self::$logger = null;
        self::$buffer = null;
        self::$initialized = false;
    }

    private static function ensureInit(): void
    {
        if (!self::$initialized) {
            self::init();
        }
    }
}