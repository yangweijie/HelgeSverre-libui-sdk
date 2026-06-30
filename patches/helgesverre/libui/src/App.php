<?php

declare(strict_types=1);

namespace Libui;

/**
 * Application lifecycle for richer apps — multiple windows, a should-quit
 * handler, and one place that owns init / main loop / uninit:
 *
 *   App::new()
 *      ->window($main)
 *      ->onShouldQuit(fn () => $document->isSaved())
 *      ->run();
 *
 * PATCHED: added afterInit() hook that fires after Ffi::init() but before
 * the event loop starts — the safe place for startup tasks that need libui
 * to be live (e.g. setting the dock icon).
 */
final class App
{
    /** @var Window[] */
    private array $windows = [];

    /** @var (callable():bool)|null */
    private $shouldQuit = null;

    /** @var list<callable(): void> */
    private array $initHooks = [];

    public static function new(): self
    {
        return new self();
    }

    /** Register a window to show when the app runs. The first one drives app lifetime. */
    public function window(Window $window): static
    {
        $this->windows[] = $window;
        return $this;
    }

    /**
     * Install a should-quit handler. Return true to allow the app to quit, false
     * to keep it running (e.g. to prompt for unsaved changes).
     */
    public function onShouldQuit(callable $cb): static
    {
        $this->shouldQuit = $cb;
        return $this;
    }

    /**
     * Register a callback to run right after libui initialises but before
     * the event loop starts — the ideal spot for startup tasks such as
     * setting the application dock icon.
     *
     * Multiple callbacks can be registered; they run in order.
     */
    public function afterInit(callable $cb): static
    {
        $this->initHooks[] = $cb;
        return $this;
    }

    /** Initialise libui, show the windows, run the loop until quit, then uninit. */
    public function run(): void
    {
        Ffi::init();

        foreach ($this->initHooks as $hook) {
            $hook();
        }

        if ($this->shouldQuit !== null) {
            Ffi::onShouldQuit($this->shouldQuit);
        }

        foreach ($this->windows as $index => $window) {
            // Closing the primary (first) window quits the app; others just close.
            if ($index === 0) {
                $window->onClosing(static function () use ($window) {
                    $window->markExternallyClosed();
                    Ffi::quit();
                    return true;
                });
            }
            $window->show();
        }

        try {
            Ffi::main();
        } finally {
            // Force PHP GC to run __destruct() on all libui wrapper objects
            // (fonts, text layouts, paths, brushes…) BEFORE uiUninit() checks
            // for leaks. Without this, uiprivUninitAlloc aborts.
            gc_collect_cycles();

            foreach ($this->windows as $window) {
                try {
                    $window->destroy();
                } catch (\Throwable) {
                    // Window may already be destroyed by the close handler.
                }
            }

            gc_collect_cycles();

            Ffi::uninit();
        }
    }
}
