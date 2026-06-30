<?php

declare(strict_types=1);

namespace Libui;

use Libui\Draw\DrawContext;
use Libui\Draw\StrokeParams;

/**
 * Manages the libui FFI instance — lazy-loading, initialisation, event loop,
 * cleanup, and a small pool of retained PHP closures that must outlive the
 * C trampolines that call back into PHP.
 *
 * PATCHED: uninit() order fixed — retained closures are cleared BEFORE
 * uiUninit() so that PHP wrapper objects holding C widget handles can be
 * GC'd before libui's leak checker fires. Without this, closures kept in
 * $retained prevent GC, __destruct() runs after uiUninit(), and libui
 * reports leaked C widgets at shutdown.
 *
 * @see https://github.com/HelgeSverre/libui
 */
class Ffi
{
    /** True after init() succeeds, false in uninit(). */
    private static bool $initialized = false;

    /**
     * The lazily-created \FFI object that wraps the loaded libui shared library.
     * In DEBUG mode (see ::debug()) the type is overridden by ::debugInit().
     */
    private static ?\FFI $ffi = null;

    /**
     * Implementation instances that Emscripten and Termoserver overrides.
     * Singleton, lazy-loaded via ::get() because ::readHeader() may call
     * ::get() during debug-init before $ffi is complete.
     */
    private static ?\FFI $impl = null;

    /**
     * In debug mode we load the stock header but read debug symbols from the
     * running lib instead of a separate file. This lets us test on any platform
     * with zero additional header management.
     */
    private static bool $debug = false;

    /**
     * Callbacks stored here survive the event loop — libui trampolines hold
     * pointers (not counted references) so PHP CC would collect them.
     *
     * Populated by ::onShouldQuit(), ::timer(), ::queueMain() and the new
     * onClosing handler; drained in ::uninit().
     *
     * @var list<callable>
     */
    private static array $retained = [];

    /** The singleton, lazily-allocated Lifecycle. */
    private static ?Lifecycle $lifecycle = null;

    public static function lifecycle(): Lifecycle
    {
        return self::$lifecycle ??= new Lifecycle();
    }

    /**
     * Determine the path to the native libui shared library.
     *
     * Resolution order:
     *   1. LIBUI_LIB env var (set by the PHAR stub when running from a packed
     *      binary, because dlopen() cannot open files inside phar://)
     *   2. Directory of the installed Composer package (vendor/)
     *   3. Parent directory of the working directory or project root
     *   4. CWD / project root
     *
     * This is intentionally not memoized so that uninit() / init() cycles can
     * serve different shared libraries during integration tests.
     */
    public static function libPath(): string
    {
        // 1. Environment variable override — always checked first
        $env = getenv('LIBUI_LIB');
        if ($env !== false && $env !== '' && is_file($env)) {
            return $env;
        }

        // Determine OS and arch
        $os = PHP_OS_FAMILY;
        $arch = strtolower(php_uname('m'));
        $isArm = str_contains($arch, 'aarch64') || str_contains($arch, 'arm');

        // 2. The Composer package's own lib/ directory
        $ref = new \ReflectionClass(self::class);
        $dir = dirname($ref->getFileName(), 2); // src/Ffi.php → vendor/helgesverre/libui

        $paths = [
            "{$dir}/lib/darwin/libui.dylib",
        ];

        if ($os === 'Windows') {
            $paths = ["{$dir}/lib/windows-x86_64/libui.dll"];
        } elseif ($os === 'Linux') {
            $paths = $isArm
                ? ["{$dir}/lib/linux-aarch64/libui.so"]
                : ["{$dir}/lib/linux-x86_64/libui.so"];
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // 3. Search from working directory upward
        $candidates = [
            getcwd() ?: '.',
            dirname(PHP_BINARY),
        ];

        $dirs = match ($os) {
            'Darwin' => ['lib/darwin'],
            'Windows' => ['lib/windows-x86_64'],
            default => $isArm
                ? ['lib/linux-aarch64']
                : ['lib/linux-x86_64'],
        };

        $names = ['libui.dylib', 'libui.dll', 'libui.so'];

        foreach ($candidates as $candidate) {
            $dir = $candidate;
            // walk up to 5 levels
            for ($i = 0; $i < 5; $i++) {
                foreach ($dirs as $sub) {
                    foreach ($names as $name) {
                        $check = "{$dir}/{$sub}/{$name}";
                        if (is_file($check)) {
                            return $check;
                        }
                    }
                }
                $parent = dirname($dir);
                if ($parent === $dir) {
                    break;
                }
                $dir = $parent;
            }
        }

        // 4. fallback — let dlopen() try the default search path
        return match ($os) {
            'Darwin' => 'libui.dylib',
            'Windows' => 'libui.dll',
            default => 'libui.so',
        };
    }

    /**
     * Read the libui C header file from the same directory as Ffi.php.
     * Under DEBUG the header is read from INSTALL_DIR/upstream/ instead.
     */
    private static function readHeader(): string
    {
        if (self::$debug) {
            // debug — the header is near the lib binary
            $libDir = dirname(self::libPath());

            return file_get_contents("{$libDir}/ui.h");
        }

        $ref = new \ReflectionClass(self::class);
        $dir = dirname($ref->getFileName()); // src/

        // try src/Native/ first (Composer package)
        $headerPath = "{$dir}/Native/libui.gen.h";
        if (is_file($headerPath)) {
            return file_get_contents($headerPath);
        }

        // fallback to parent src/
        $headerPath = dirname($dir) . '/src/Native/libui.gen.h';
        if (is_file($headerPath)) {
            return file_get_contents($headerPath);
        }

        throw new \RuntimeException('Cannot find libui.h header file.');
    }

    /**
     * Initialise the libui FFI binding.
     *
     * Loads the native C library via FFI::cdef(), calls uiInitOptions() and
     * uiInit(), and retains the event loop's should-quit callback so that PHP's
     * garbage collector does not collect it while C holds a reference to it.
     *
     * Idempotent: safe to call multiple times in the same process. Only the
     * first call actually initialises; subsequent calls return immediately.
     */
    public static function init(bool $debug = false): void
    {
        if (self::$initialized) {
            return;
        }

        self::$debug = $debug;

        $header = self::readHeader();
        $libPath = self::libPath();
        error_clear_last();

        if ($debug) {
            self::debugInit($header, $libPath);
        } else {
            self::$ffi = \FFI::cdef($header, $libPath);
        }

        /** @var \FFI $ffi */
        $ffi = self::get();

        $opts = $ffi->new('uiInitOptions');
        $ffi->uiInit(\FFI::addr($opts));

        self::$initialized = true;

        // Retain the should-quit closure to prevent GC collection.
        $cb = static function (): void {
            // no-op; App registers its own onShouldQuit handler.
        };
        self::$retained[] = $cb;
    }

    /** Return true after a successful init(). */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Return the \FFI instance, loading it first if needed.
     *
     * In normal use ::init() is called explicitly (e.g. from the demo script
     * or from App::run()), but we also lazy-load here for convenience in
     * scripts that only call a handful of FFI methods.
     */
    public static function get(): \FFI
    {
        if (self::$ffi === null) {
            self::init();
        }

        return self::$ffi;
    }

    /**
     * Returns the package root directory.
     *
     * @return string The absolute path to the package root (directory containing src/)
     */
    public static function root(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Upcast a widget-specific handle (uiButton *, etc.) to uiControl *.
     *
     * @param \FFI\CData $handle The widget-specific handle
     * @return \FFI\CData The upcast handle as uiControl *
     */
    public static function control(\FFI\CData $handle): \FFI\CData
    {
        return self::get()->cast('uiControl *', $handle);
    }

    /**
     * Allocates a C value or struct of the given type.
     *
     * @param string $type The C type to allocate (e.g., 'uiAreaHandler', 'double[4]')
     * @param bool $owned Whether the C memory is owned by PHP (default: true)
     * @return \FFI\CData The allocated C data
     */
    public static function new(string $type, bool $owned = true): \FFI\CData
    {
        return self::get()->new($type, $owned);
    }

    /**
     * Copies an owned C string into PHP and frees it with uiFreeText.
     *
     * @param \FFI\CData|null $ptr Pointer to the C string, or null
     * @return string The copied string content, or empty string if $ptr is null
     */
    public static function ownedString(?\FFI\CData $ptr): string
    {
        if ($ptr === null) {
            return '';
        }
        $value = \FFI::string($ptr);
        self::get()->uiFreeText($ptr);
        return $value;
    }

    /**
     * Copies a borrowed C string into PHP without freeing it.
     *
     * @param \FFI\CData|null $ptr Pointer to the C string, or null
     * @return string The copied string content, or empty string if $ptr is null
     */
    public static function borrowedString(?\FFI\CData $ptr): string
    {
        return $ptr === null ? '' : \FFI::string($ptr);
    }

    /**
     * Enter the libui event loop; blocks until the application quits.
     */
    public static function main(): void
    {
        self::get()->uiMain();
    }

    /**
     * Signal the libui event loop to exit; typically called inside an
     * onShouldQuit or onClosing callback.
     */
    public static function quit(): void
    {
        self::get()->uiQuit();
    }

    /**
     * Register a should-quit callback that libui calls when the OS requests
     * the application to quit (e.g. Cmd+Q, or closing the last window). Return
     * true to allow the quit, false to deny it.
     *
     * The callback is retained in self::$retained so the GC does not collect it
     * while C holds an un-tracked function pointer to it.
     */
    public static function onShouldQuit(callable $callback): void
    {
        // Wrap in a closure so we can retain it.
        $cb = static function () use ($callback): int {
            try {
                return $callback() ? 1 : 0;
            } catch (\Throwable $e) {
                fwrite(STDERR, "[onShouldQuit] {$e->getMessage()}\n");

                return 1; // allow quit on error
            }
        };

        self::$retained[] = $cb;
        self::get()->uiOnShouldQuit($cb, null);
    }

    /**
     * Tear down the libui FFI binding.
     *
     * Frees any leftover libui resources (TableModel, Image) via Lifecycle,
     * then calls uiUninit(). The leak checker inside uiUninit() aborts if it
     * finds libui-managed allocations that were never freed — this is usually
     * caused by PHP objects whose C handles were not destroyed before the
     * event-loop shutdown.
     *
     * PATCHED order: retained closures are cleared BEFORE uiUninit(), letting
     * PHP collect the wrapper objects and their __destruct() calls free any
     * lingering C handles. The original order ran retained = [] after
     * uiUninit(), which was too late — closures kept wrappers alive, their
     * __destruct() ran after the leak check, and libui aborted on shutdown.
     *
     * Ffi::main() returns, typically via App::run() which handles this
     * automatically.
     *
     * Note: After calling uninit(), you must call init() again before using
     * libui and wipe the ENV of any widgets created before uninit().
     */
    public static function uninit(): void
    {
        // PATCHED: clear retained closures first so PHP GC can collect widget
        // wrappers and run __destruct() BEFORE uiUninit()'s leak checker fires.
        //
        // The native trampolines libui held pointers to are dead once the loop
        // exits; dropping our retainers now is safe because no callback can
        // fire after Ffi::main() has returned.
        self::$retained = [];
        Control::clearRetainedCallbacks();

        // Force PHP GC to collect wrappers whose closures were just released.
        // Without this, __destruct() — which frees C handles — runs AFTER
        // uiUninit(), and the leak checker aborts from uiprivUninitAlloc.
        //
        // Run GC multiple times: the first pass collects objects whose refcount
        // dropped to 0 via the retained cleanup; __destruct() from that pass may
        // release further references, requiring a second pass to collect those.
        // In micro.sfx (phpmicro) the GC is less aggressive than CLI, so we need
        // extra passes to ensure all libui wrapper objects are collected before
        // uiUninit()'s leak checker fires.
        \gc_collect_cycles();
        \gc_collect_cycles();
        \gc_collect_cycles();

        // Free any forgotten TableModels/Images before libui's leak check.
        Lifecycle::freeAll();

        self::get()->uiUninit();
        self::$initialized = false;
        Window::resetMenuLock(); // a fresh session after uninit() may build menus again
    }

    /**
     * Queue a callback to run once on the main thread, on the next loop tick.
     *
     * The callback takes no arguments and its return value is ignored. Any
     * exception is caught and reported to STDERR — a throw escaping into the C
     * trampoline would be a hard fatal.
     */
    public static function queueMain(callable $fn): void
    {
        $cb = static function ($data) use ($fn): void {
            try {
                $fn();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[queueMain] {$e->getMessage()}\n");
            }
        };
        self::$retained[] = $cb;
        self::get()->uiQueueMain($cb, null);
    }

    /**
     * Run a callback repeatedly every $milliseconds on the main thread.
     *
     * Return true (or null) from $fn to keep firing, false to stop the timer.
     * Exceptions are caught, reported to STDERR, and stop the timer.
     */
    public static function timer(int $milliseconds, callable $fn): void
    {
        $cb = static function ($data) use ($fn): int {
            try {
                return $fn() === false ? 0 : 1;
            } catch (\Throwable $e) {
                fwrite(STDERR, "[timer] {$e->getMessage()}\n");

                return 0; // stop on error
            }
        };
        self::$retained[] = $cb;
        self::get()->uiTimer($milliseconds, $cb, null);
    }

    /**
     * Exit the program immediately with an optional message.
     */
    public static function exit(string $message = ''): void
    {
        if ($message !== '') {
            fwrite(STDERR, $message . "\n");
        }
        exit(1);
    }

    // ── DEBUG support ──

    /**
     * Enable debug mode (see ::init() and ::initDebug()).
     */
    public static function debug(): bool
    {
        return self::$debug;
    }

    /**
     * Under DEBUG the header file path is overridden to point at the
     * upstream ui.h (shipped with libui-ng) and the \FFI instance wraps
     * the same shared library as production — no separate header file needed.
     */
    private static function debugInit(string $header, string $libPath): void
    {
        $header = self::readHeader();

        self::$ffi = \FFI::cdef($header, $libPath);
    }
}