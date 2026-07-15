<?php

declare(strict_types=1);

use Yangweijie\Ui2\Rendering\WidgetRenderer\WebViewSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\WebViewRenderer;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\FillRoundedRect;
use Yangweijie\Ui2\Widgets\WebViewComponent;
use Yangweijie\Ui2\WebView;

test('WebViewSpec reports the webview type', function () {
    $spec = new WebViewSpec(url: 'https://example.com');
    expect($spec->type())->toBe('webview');
    expect($spec->url)->toBe('https://example.com');
    expect($spec->html)->toBeNull();
    expect($spec->binds)->toBe([]);
});

test('WebViewRenderer paints a placeholder fill for the node rect', function () {
    $renderer = new WebViewRenderer();
    $spec = new WebViewSpec(html: '<h1>hi</h1>', background: '0xEEEEEE');
    $list = $renderer->render($spec, new DesignTokens(), 320.0, 200.0);

    expect($list)->toBeInstanceOf(RenderCommandList::class);
    $commands = $list->commands;
    expect($commands)->toHaveCount(1);
    expect($commands[0])->toBeInstanceOf(FillRoundedRect::class);
    // The placeholder covers the full node rect.
    expect($commands[0]->width)->toBe(320.0);
    expect($commands[0]->height)->toBe(200.0);
});

test('WebViewRenderer ignores non-WebViewSpec', function () {
    $renderer = new WebViewRenderer();
    // Any other spec type yields an empty command list (renderers are keyed by type()).
    $list = $renderer->render(new \Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec('x'), new DesignTokens(), 10.0, 10.0);
    expect($list->commands)->toHaveCount(0);
});

test('WebViewComponent is a drop-in WebView with source auto-detection', function () {
    expect(class_exists(WebViewComponent::class))->toBeTrue();
    expect(is_subclass_of(WebViewComponent::class, WebView::class))->toBeTrue();
    expect(method_exists(WebViewComponent::class, 'setSource'))->toBeTrue();
});
