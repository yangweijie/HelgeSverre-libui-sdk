<?php

declare(strict_types=1);

/**
 * WidgetRenderer 注册表自绘演示
 * ────────────────────────────────────────────────────────────────────────
 * 基于 Yangweijie\Ui2\Rendering\WidgetRenderer\*：
 *   - 按钮由 ButtonRenderer 自绘（filled / soft / outline / disabled / pressed 变体）
 *   - 原生 fallback 按钮使用同一 API，仅 preferNative=true 即可切换
 *   - 卡片由 CardRenderer 自绘（表面填充 + 伪阴影 elevation + 可选边框）
 * 主题通过 DesignTokens::applyTheme 深合并切换。
 * 自绘按钮（RendererButton）是非滚动 Area（同 ToggleSwitch/StatusIndicator），
 * 无 intrinsic 尺寸，因此在行里用 Build::stretchy() 放置以获得占位；按钮填满
 * 分配到的空间，命中区与可见区一致。
 *
 * 运行： php85 examples/renderer-button-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\AreaDelegate;
use Libui\Build;
use Libui\Button;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Rendering\CommandExecutor;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardRenderer;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RendererRegistry;
use Yangweijie\Ui2\Widgets\RendererButton;

$darkOverrides = [
    'color' => [
        'primary'      => [0.30, 0.65, 1.0, 1.0],
        'track'        => [0.22, 0.22, 0.24, 1.0],
        'onSurface'    => [0.92, 0.92, 0.92, 1.0],
        'surface'      => [0.16, 0.16, 0.18, 1.0],
        'knob'         => [0.95, 0.95, 0.95, 1.0],
        'toggleOn'     => [0.30, 0.65, 1.0, 1.0],
        'toggleOff'    => [0.40, 0.40, 0.45, 0.5],
        'toggleBorder' => [0.50, 0.50, 0.50, 0.6],
        'knobBorder'   => [0.30, 0.30, 0.30, 0.4],
    ],
];

$registry = RendererRegistry::default();

// Self-drawn row (renderer-driven)
$self = [
    'filled'  => new RendererButton('保存', 'filled', true, null, $registry, false),
    'soft'    => new RendererButton('次要', 'soft', true, null, $registry, false),
    'outline' => new RendererButton('取消', 'outline', true, null, $registry, false),
    'disabled'=> new RendererButton('禁用', 'filled', false, null, $registry, false),
];

// Native fallback row (same API, but preferNative=true → Libui\Button)
$native = [
    new RendererButton('原生·保存', 'filled', true, null, $registry, true),
    new RendererButton('原生·取消', 'outline', true, null, $registry, true),
];

$status = new Label('点击按钮 · 切换主题看自绘按钮换色');

// A card drawn directly by CardRenderer inside an Area (proves renderers are
// reusable by any control, not just RendererButton). Use a non-scrolling Area
// so it fills the stretchy row allocated by the vbox.
$cardDelegate = new class extends AreaDelegate {
    public function draw(DrawContext $ctx, AreaDrawParams $params): void
    {
        $list = (new CardRenderer())->render(
            new CardSpec(bordered: true, elevation: 1.0),
            new DesignTokens(),
            $params->areaWidth,
            $params->areaHeight,
        );
        (new CommandExecutor())->execute($ctx, $list);
        $list->free();
    }
};
$cardArea = new Area($cardDelegate);

$self['filled']->on('click', static fn () => $status->setText('保存 被点击（自绘）'));
$self['soft']->on('click', static fn () => $status->setText('次要 被点击（自绘）'));
$self['outline']->on('click', static fn () => $status->setText('取消 被点击（自绘）'));
$native[0]->on('click', static fn () => $status->setText('原生·保存 被点击（原生 Button）'));
$native[1]->on('click', static fn () => $status->setText('原生·取消 被点击（原生 Button）'));

$themeBtn = new Button('切换主题');
$theme = 'light';
$themeBtn->onClicked(static function () use (&$theme, $self, $darkOverrides): void {
    $theme = $theme === 'light' ? 'dark' : 'light';
    $overrides = $theme === 'dark' ? $darkOverrides : [];
    foreach ($self as $b) {
        $b->setTheme($overrides);
    }
});

$top = Build::hbox($themeBtn, Build::stretchy(new Label('')));
// Self-drawn buttons are non-scrolling Areas with no intrinsic size, so they
// must be placed stretchy to get a real footprint; native buttons hug content.
$rowButtons = Build::hbox(
    Build::stretchy($self['filled']->root()),
    Build::stretchy($self['soft']->root()),
    Build::stretchy($self['outline']->root()),
    Build::stretchy($self['disabled']->root()),
    $native[0]->root(),
    $native[1]->root(),
);

$window = new Window('WidgetRenderer 自绘演示', 820, 540, false);
$window->setMargined(true);
$window->centered();
$window->setChild(Build::vbox(
    $top,
    new Label('自绘按钮 + 原生 fallback（同一 API）:'),
    $rowButtons,
    new Label('卡片（CardRenderer 直接画在 Area 上）:'),
    Build::stretchy($cardArea),
    $status,
));

App::new()
    ->window($window)
    ->onShouldQuit(static fn (): bool => true)
    ->run();
