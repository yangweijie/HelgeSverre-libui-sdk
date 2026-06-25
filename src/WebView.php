<?php

declare(strict_types=1);

namespace Yangweijie\Ui2;

use Libui\Ffi;
use Libui\Window;

/**
 * Embedded WebView — wraps a native browser engine inside a libui Window.
 *
 * Instead of replacing the entire content view (which would destroy libui's
 * layout), this creates a **borderless child window** positioned at the
 * specified area within the libui parent, creating an "iframe-style" embed:
 *
 *     ┌─ libui Window ────────────────────────────┐
 *     │  ┌─ libui VBox/HBox ────────────────────┐ │
 *     │  │  [Sidebar]  [Label placeholder]      │ │
 *     │  └──────────────────────────────────────┘ │
 *     │  ┌─ child window (borderless) ──────────┐ │
 *     │  │  WKWebView / WebKitGTK / WebView2    │ │
 *     │  │  ← navigate() / setHtml() / eval()   │ │
 *     │  └──────────────────────────────────────┘ │
 *     └───────────────────────────────────────────┘
 *
 * Usage:
 *
 *     $webview = new WebView($window, 280, 0, 800, 600);
 *     $webview->navigate('https://example.com');
 *     // or
 *     $webview->setHtml('<h1>Hello</h1>');
 *
 *     // Bind a JS-callable function
 *     $webview->bind('ping', function (string $id, string $req) use ($webview) {
 *         $webview->return($id, 0, json_encode(['ok' => true]));
 *     });
 *
 *     // Handle window resize
 *     $webview->autoResize($window, 280, 0);
 *
 * The webview is automatically destroyed when the Window closes via
 * Window::onClosing(), or manually via destroy() / __destruct().
 *
 * @requires kingbes/pebview (composer dependency)
 * @requires compiled bridge library in bridge/ directory
 *
 * @see bridge/webview_bridge.m   (macOS)
 * @see bridge/webview_bridge_linux.c
 * @see bridge/webview_bridge_win.c
 */
class WebView
{
    /** @var \FFI Bridge FFI instance (wvb_create/move/destroy) */
    private \FFI $bridge;

    /** @var \FFI PebView FFI instance (webview_set_html/navigate/bind/...) */
    private \FFI $pv;

    /** @var \FFI\CData|null The webview_t pointer */
    private ?\FFI\CData $handle = null;

    /** @var array<int, callable> Retained FFI callback trampolines */
    private array $callbacks = [];

    /** @var bool Whether the webview has been destroyed */
    private bool $destroyed = false;

    /** @var string Path to the bridge library */
    private string $bridgeLib;

    /** @var string Path to the PebView library */
    private string $pebviewLib;

    /**
     * Cached NSView/contentView handle (from uiControlHandle).
     *
     * @var int<0, max>
     */
    private int $parentHandle;

    /** X position (left edge of the webview, relative to libui content area). */
    private int $x;

    /** Y position (top edge of the webview, relative to libui content area). */
    private int $y;

    /** Webview width in points/pixels. */
    private int $width;

    /** Webview height in points/pixels. */
    private int $height;

    /**
     * Create an embedded webview inside a libui Window.
     *
     * @param Window $window The parent libui Window. Must be shown first
     *                       if you want this to appear immediately.
     * @param int    $x      X offset from the left of the window's content area.
     * @param int    $y      Y offset from the top of the window's content area.
     * @param int    $width  Width of the webview area in points/pixels.
     * @param int    $height Height of the webview area in points/pixels.
     * @param bool   $debug  Enable Web Inspector / DevTools (default: false).
     *
     * @throws \RuntimeException If the bridge or PebView library cannot be loaded,
     *                           or if wvb_create() returns null.
     */
    public function __construct(
        Window $window,
        int    $x = 0,
        int    $y = 0,
        int    $width = 800,
        int    $height = 600,
        bool   $debug = false,
    ) {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;

        $this->resolveLibraryPaths();
        $this->loadBridge();
        $this->loadPebView();
        $this->parentHandle = $this->resolveParentHandle($window);

        $this->handle = $this->bridge->wvb_create(
            $debug ? 1 : 0,
            $this->parentHandle,
            $this->x,
            $this->y,
            $this->width,
            $this->height,
        );

        if ($this->handle === null) {
            throw new \RuntimeException(
                'WebView creation failed (wvb_create returned null). '
                . 'Ensure the bridge library is compiled for this platform.',
            );
        }
    }

    /**
     * Destroy the webview on object destruction if not already destroyed.
     */
    public function __destruct()
    {
        $this->destroy();
    }

    // ────── Navigation / Content ──────

    /**
     * Navigate the webview to a URL.
     *
     * @return $this
     */
    public function navigate(string $url): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_navigate($this->handle, $url);
        return $this;
    }

    /**
     * Set the webview content to raw HTML.
     *
     * @return $this
     */
    public function setHtml(string $html): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_set_html($this->handle, $html);
        return $this;
    }

    /**
     * Initialise the webview with a JS snippet (runs before any page load).
     *
     * @return $this
     */
    public function init(string $js): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_init($this->handle, $js);
        return $this;
    }

    // ────── JavaScript Evaluation ──────

    /**
     * Evaluate arbitrary JavaScript in the webview.
     *
     * @return $this
     */
    public function eval(string $js): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_eval($this->handle, $js);
        return $this;
    }

    // ────── JS ↔ PHP Bridge ──────

    /**
     * Bind a PHP function as a callable JS function.
     *
     * The callback receives (string $id, string $req) where $id is the
     * async call ID and $req is the JSON-encoded request payload.
     * Call {@see return()} to send the result back to JS.
     *
     *     $wv->bind('ping', function (string $id, string $req) use ($wv) {
     *         $wv->return($id, 0, json_encode(['ok' => true]));
     *     });
     *
     * CRITICAL: The callback MUST catch all exceptions. Exceptions thrown
     * through the C FFI trampoline will crash the process.
     *
     * @param string   $name    JS function name (e.g. 'ping').
     * @param callable $handler (string $id, string $req) => void
     *
     * @return $this
     */
    public function bind(string $name, callable $handler): static
    {
        $this->assertNotDestroyed();

        if ($this->pv === null || $this->handle === null) {
            throw new \RuntimeException('WebView not initialised');
        }

        $cb = function ($id, $req, $_arg) use ($handler) {
            try {
                $handler($id, $req);
            } catch (\Throwable $e) {
                \fwrite(\STDERR, "[WebView] Error in bound function '{$name}': {$e->getMessage()}\n");
            }
        };

        $this->callbacks[] = $cb;
        $this->pv->webview_bind($this->handle, $name, $cb, null);

        return $this;
    }

    /**
     * Unbind a previously bound JS function.
     *
     * @return $this
     */
    public function unbind(string $name): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_unbind($this->handle, $name);
        return $this;
    }

    /**
     * Return a result to a JS async call.
     *
     * Must be called inside a bound callback to respond to the JS caller.
     *
     * @param string $id     The call ID from the bound callback.
     * @param int    $status 0 = success, non-zero = error.
     * @param string $result JSON-encoded result string.
     *
     * @return $this
     */
    public function return(string $id, int $status, string $result): static
    {
        $this->assertNotDestroyed();
        $this->pv->webview_return($this->handle, $id, $status, $result);
        return $this;
    }

    // ────── Window Resize Integration ──────

    /**
     * Automatically reposition the webview when the parent Window is resized.
     *
     * The x offset is typically the sidebar width; the webview fills the
     * remaining space horizontally and all available vertical space.
     *
     * @param Window $window      The parent window.
     * @param int    $xOffset     Left margin (e.g. sidebar width).
     * @param int    $yOffset     Top margin.
     * @param int    $hMargin     Right margin subtracted from width.
     * @param int    $vMargin     Bottom margin subtracted from height.
     *
     * @return $this
     */
    public function autoResize(
        Window $window,
        int    $xOffset = 0,
        int    $yOffset = 0,
        int    $hMargin = 0,
        int    $vMargin = 0,
    ): static {
        $this->assertNotDestroyed();

        $winHandle = $window->handle();
        $resizeCb = function ($w) use ($xOffset, $yOffset, $hMargin, $vMargin) {
            $wOut = Ffi::get()->new('int');
            $hOut = Ffi::get()->new('int');
            Ffi::get()->uiWindowContentSize($w, \FFI::addr($wOut), \FFI::addr($hOut));
            $newW = (int) $wOut->cdata;
            $newH = (int) $hOut->cdata;

            $wvW = \max(200, $newW - $xOffset - $hMargin);
            $wvH = \max(200, $newH - $yOffset - $vMargin);

            $this->reposition($xOffset, $yOffset, $wvW, $wvH);
        };

        $this->callbacks[] = $resizeCb;
        Ffi::get()->uiWindowOnContentSizeChanged($winHandle, $resizeCb, null);

        return $this;
    }

    /**
     * Manually reposition/resize the webview.
     *
     * @return $this
     */
    public function reposition(int $x, int $y, int $width, int $height): static
    {
        $this->assertNotDestroyed();
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;

        if ($this->handle !== null) {
            $this->bridge->wvb_move(
                $this->handle,
                $this->parentHandle,
                $x, $y, $width, $height,
            );
        }

        return $this;
    }

    // ────── Lifecycle ──────

    /**
     * Register a close handler on the parent Window that destroys the webview
     * before the window closes. Call this if you're NOT using auto-cleanup.
     *
     * @return $this
     */
    public function cleanupOnClose(Window $window): static
    {
        $this->assertNotDestroyed();

        $winHandle = $window->handle();
        $closingCb = function () {
            $this->destroy();
            return 1; // allow close
        };

        $this->callbacks[] = $closingCb;
        Ffi::get()->uiWindowOnClosing($winHandle, $closingCb, null);

        return $this;
    }

    /**
     * Destroy the webview and its child window.
     *
     * Safe to call multiple times — subsequent calls are no-ops.
     */
    public function destroy(): void
    {
        if ($this->destroyed || $this->handle === null) {
            return;
        }

        try {
            $this->bridge->wvb_destroy($this->handle);
        } catch (\Throwable $e) {
            \fwrite(\STDERR, "[WebView] Error during destroy: {$e->getMessage()}\n");
        }

        $this->handle = null;
        $this->callbacks = [];
        $this->destroyed = true;
    }

    /**
     * Whether this WebView has been destroyed.
     */
    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    // ────── Position getters ──────

    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    // ────── Internal ──────

    /**
     * Resolve the platform-specific library paths.
     */
    private function resolveLibraryPaths(): void
    {
        $base = \dirname(__DIR__);

        $this->bridgeLib = match (\PHP_OS_FAMILY) {
            'Darwin' => $base . '/bridge/webview_bridge.dylib',
            'Linux'  => $base . '/bridge/webview_bridge.so',
            'Windows' => $base . '/bridge/webview_bridge.dll',
            default  => throw new \RuntimeException('Unsupported platform: ' . \PHP_OS_FAMILY),
        };

        $this->pebviewLib = match (\PHP_OS_FAMILY) {
            'Darwin' => $base . '/vendor/kingbes/pebview/lib/macos/arm64/PebView.dylib',
            'Linux'  => $base . '/vendor/kingbes/pebview/lib/linux/x86_64/libPebView.so',
            'Windows' => $base . '/vendor/kingbes/pebview/lib/windows/x64/PebView.dll',
            default  => throw new \RuntimeException('Unsupported platform: ' . \PHP_OS_FAMILY),
        };
    }

    /**
     * Load the bridge FFI (wvb_create/move/destroy).
     */
    private function loadBridge(): void
    {
        $this->bridge = \FFI::cdef(
            'void* wvb_create(int debug, uintptr_t parent_handle, int x, int y, int w, int h);'
            . 'void  wvb_move(void* wv, uintptr_t parent_handle, int x, int y, int w, int h);'
            . 'void  wvb_destroy(void* wv);',
            $this->bridgeLib,
        );
    }

    /**
     * Load the PebView FFI (webview_set_html/navigate/bind/...).
     */
    private function loadPebView(): void
    {
        $headerPath = \dirname(__DIR__) . '/vendor/kingbes/pebview/include/PebView.h';

        if (!\file_exists($headerPath)) {
            throw new \RuntimeException(
                'PebView header not found at ' . $headerPath . '. '
                . 'Run `composer install` first.',
            );
        }

        $this->pv = \FFI::cdef(
            \file_get_contents($headerPath),
            $this->pebviewLib,
        );
    }

    /**
     * Get the native handle from a Window for the bridge.
     *
     * uiControlHandle() on a uiWindow returns:
     *   - macOS: NSView* (the contentView)
     *   - Linux: GtkWidget* (the window widget)
     *   - Windows: HWND (the window handle)
     *
     * @return int<0, max>
     */
    private function resolveParentHandle(Window $window): int
    {
        return Ffi::get()->uiControlHandle($window->asControl());
    }

    /**
     * @throws \RuntimeException If the webview has been destroyed.
     */
    private function assertNotDestroyed(): void
    {
        if ($this->destroyed) {
            throw new \RuntimeException('WebView has been destroyed');
        }
    }
}