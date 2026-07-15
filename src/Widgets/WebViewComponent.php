<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Window;
use Yangweijie\Ui2\WebView;

/**
 * A drop-in, generic WebView widget for the {@see Window}-level embedding model.
 *
 * Unlike {@see TreeView} / {@see CodeEditor} (which bake in a specific HTML
 * asset + domain behaviour), this is the bare browser: point it at a URL or
 * some HTML and it shows up. It is a thin, convenience-oriented subclass of
 * {@see WebView} — all the navigation / JS-bridge / resize methods live there
 * and are inherited unchanged.
 *
 * ```php
 * // URL vs HTML is auto-detected from the source string.
 * $wv = new WebViewComponent($win, 280, 0, 800, 600, 'https://example.com', true);
 * $wv->bind('ping', fn (string $id, string $req) => $wv->return($id, 0, '{}'));
 * $wv->autoResize($win, 280, 0);
 * ```
 *
 * @see WebView The engine this builds on.
 */
class WebViewComponent extends WebView
{
    /**
     * @param Window      $window The parent libui Window (shown first for the
     *                           webview to appear immediately).
     * @param int         $x      X offset from the window's content-area left.
     * @param int         $y      Y offset from the window's content-area top.
     * @param int         $w      Width.
     * @param int         $h      Height.
     * @param string|null $src    Initial URL or HTML; null = blank. A string
     *                           matching a URI scheme (http(s)://, file://,
     *                           about:) is navigated, everything else is setHtml().
     * @param bool        $debug  Enable Web Inspector / DevTools.
     */
    public function __construct(
        Window      $window,
        int         $x = 0,
        int         $y = 0,
        int         $w = 800,
        int         $h = 600,
        ?string     $src = null,
        bool        $debug = false,
    ) {
        parent::__construct($window, $x, $y, $w, $h, $debug);

        if ($src !== null) {
            $this->setSource($src);
        }
    }

    /**
     * Load a URL or raw HTML, auto-detecting which from the source string.
     *
     * @return $this
     */
    public function setSource(string $src): static
    {
        if (\preg_match('#^[a-z][a-z0-9+.\-]*://#i', $src) || \str_starts_with($src, 'about:')) {
            $this->navigate($src);
        } else {
            $this->setHtml($src);
        }

        return $this;
    }
}
