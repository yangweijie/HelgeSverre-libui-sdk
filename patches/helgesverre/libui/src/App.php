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
 * For a single window with no app-level concerns, prefer {@see Window::run()}.
 */
final class App
{
    /** @var Window[] */
    private array $windows = [];

    /** @var (callable():bool)|null */
    private $shouldQuit = null;

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

    /** Initialise libui, show the windows, run the loop until quit, then uninit. */
    public function run(): void
    {
        Ffi::init();

        if ($this->shouldQuit !== null) {
            Ffi::onShouldQuit($this->shouldQuit);
        }

        foreach ($this->windows as $index => $window) {
            // Closing the primary (first) window quits the app; others just close.
            if ($index === 0) {
                $window->onClosing(static function () {
                    Ffi::quit();
                    return true;
                });
            }
            $window->show();
        }

        try {
            Ffi::main();
        } finally {
            // Destroy all windows before uninit() so libui's leak check passes.
            // Without this, PHP GC collects the Window wrappers after uninit(),
            // at which point FFI is dead and uiprivUninitAlloc() reports leaks.
            foreach ($this->windows as $window) {
                try {
                    $window->destroy();
                } catch (\Throwable) {
                    // Window may already be destroyed by the close handler.
                }
            }
            Ffi::uninit();
        }
    }
}
