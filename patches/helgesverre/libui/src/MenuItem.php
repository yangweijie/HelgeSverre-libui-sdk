<?php

declare(strict_types=1);

namespace Libui;

/**
 * MenuItem widget. Hand-editable — add convenience methods here.
 * Inherits the generated API from Generated\\MenuItem.
 *
 * PATCHED:
 * - onClick() now replaces any previously registered handler instead of
 *   appending another C-level callback. A single internal trampoline
 *   dispatches to whichever PHP callback was set last.
 * - Errors are no longer silently swallowed: an optional per-call error
 *   handler or a global default handler can intercept exceptions. Falls
 *   back to STDERR only when neither is configured.
 *
 * @see \Libui\Generated\MenuItem
 */
class MenuItem extends Generated\MenuItem
{
    /** The current click handler, or null if none. */
    private ?\Closure $onClickHandler = null;

    /** Per-call error handler, or null to fall through to the global default. */
    private ?\Closure $onClickUserError = null;

    /** Whether the single C trampoline has been registered. */
    private bool $onClickRegistered = false;

    /**
     * Global fallback error handler for every MenuItem click event.
     *
     * When set, this is invoked for any exception thrown inside a click
     * handler, unless a per-call handler was given to onClick(). When null
     * (the default), exceptions are written to STDERR.
     */
    private static ?\Closure $defaultErrorHandler = null;

    /**
     * Set (or clear) the global error handler for all MenuItem click events.
     *
     * @param callable(\Throwable):void|null $handler  Receives the caught
     *     exception. Pass null to restore the STDERR fallback.
     */
    public static function setDefaultErrorHandler(?callable $handler): void
    {
        self::$defaultErrorHandler = $handler !== null
            ? \Closure::fromCallable($handler)
            : null;
    }

    /** Re-wrap a generated MenuItem handle as a hand-written Libui\MenuItem. */
    public static function fromGenerated(Generated\MenuItem $g): self
    {
        return self::wrap($g->handle());
    }

    /**
     * Register a click handler that receives only this typed MenuItem.
     *
     * Unlike the raw onClicked(), this hides libui's raw uiWindow* second
     * argument (which must never be passed to the Dialogs/Ui facade). Capture
     * your typed Window via `use ($window)` if you need it for dialogs.
     *
     *   $item->onClick(fn (MenuItem $item) => $item->setChecked(! $item->checked()));
     *
     * Unlike the upstream version, calling onClick() a second time REPLACES
     * the previous handler — it does NOT register an additional C callback.
     *
     * @param callable(MenuItem):void $cb       Click handler.
     * @param callable(\Throwable):void|null $onError  Optional per-call
     *     error handler. When null, falls back to the global default handler
     *     (see {@see setDefaultErrorHandler()}), and if that is also null,
     *     writes the exception message to STDERR.
     */
    public function onClick(callable $cb, ?callable $onError = null): static
    {
        $this->onClickHandler = \Closure::fromCallable($cb);
        $this->onClickUserError = $onError !== null
            ? \Closure::fromCallable($onError)
            : null;

        if (! $this->onClickRegistered) {
            $this->onClickRegistered = true;

            $fn = static::keep(function ($sender, $window, $data): void {
                try {
                    ($this->onClickHandler)($this);
                } catch (\Throwable $e) {
                    $this->dispatchError($e);
                }
            });

            Ffi::get()->uiMenuItemOnClicked($this->handle(), $fn, null);
        }

        return $this;
    }

    /**
     * Remove any previously registered click handler.
     *
     * The C-level trampoline remains registered (libui-ng does not provide a
     * removal API), but the dispatched callback becomes a no-op, so no user
     * code will run.
     */
    public function removeOnClick(): static
    {
        $this->onClickHandler = null;
        return $this;
    }

    /**
     * Dispatch an exception to the most specific available handler.
     *
     * Resolution order:
     * 1. Per-call error handler (passed to onClick())
     * 2. Global default handler (setDefaultErrorHandler())
     * 3. STDERR fallback
     */
    private function dispatchError(\Throwable $e): void
    {
        if ($this->onClickUserError !== null) {
            ($this->onClickUserError)($e);
            return;
        }

        if (self::$defaultErrorHandler !== null) {
            (self::$defaultErrorHandler)($e);
            return;
        }

        \fwrite(\STDERR, "[onClick] {$e->getMessage()}\n");
    }
}
