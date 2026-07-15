<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Rendering\WidgetRenderer;

/**
 * Embeds a live WebView as a leaf node inside a {@see \Yangweijie\Ui2\Widgets\Surface}
 * layout tree.
 *
 * A WebView is a *native borderless child window*, not a draw command — it
 * cannot be painted into the Surface's DrawContext. So this spec plays a
 * double role:
 *
 *   1. The {@see WebViewRenderer} paints a lightweight placeholder rectangle
 *      (shown for the single frame before the real browser window appears).
 *   2. {@see \Yangweijie\Ui2\Widgets\Surface} detects WebViewSpec nodes every
 *      frame and keeps a real {@see \Yangweijie\Ui2\WebView} child window glued
 *      to the node's on-screen rect — exactly like the IME text overlay.
 *
 * Provide either $url (navigated) or $html (rendered); $src auto-detection is
 * available via {@see \Yangweijie\Ui2\Widgets\WebViewComponent}.
 *
 * ```php
 * $layout = LayoutNode::column()
 *     ->child(LayoutNode::leaf('hdr', new LabelSpec('Browser'), height: 30))
 *     ->child(LayoutNode::leaf('web', new WebViewSpec(
 *         url: 'https://example.com',
 *         binds: ['ping' => fn (string $id, string $req) => /* ... *\/],
 *     ), height: 400));
 *
 * $surface = new Surface($layout);
 * // later: $surface->webviewOf('web')->eval('document.title');
 * ```
 */
final class WebViewSpec extends WidgetSpec
{
    /**
     * @param string|null                 $url     URL to navigate to (takes
     *                                             precedence over $html).
     * @param string|null                 $html    Raw HTML to render.
     * @param bool                        $debug   Enable Web Inspector / DevTools.
     * @param string|null                 $background Placeholder fill (hex int),
     *                                             null = default light panel.
     * @param array<string, callable>     $binds   JS function name => PHP handler
     *                                             (string $id, string $req): void.
     *                                             Applied once at creation; change
     *                                             via {@see Surface::webviewOf()}.
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $html = null,
        public readonly bool $debug = false,
        public readonly ?string $background = null,
        public readonly array $binds = [],
    ) {
    }

    public function type(): string
    {
        return 'webview';
    }
}
