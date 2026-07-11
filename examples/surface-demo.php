<?php

declare(strict_types=1);

/**
 * Surface 自绘画布演示（含 Phase 8 事件系统）
 * ──────────────────────────────────────────────────────────────────────
 * 一个 Surface（单块非滚动 Area）用自建 FlexLayout 布局并自绘整棵树：
 *   - 一行 4 个自绘按钮（filled / soft / outline / disabled）
 *   - 一张 CardRenderer 自绘卡片（固定高度 120，避免空卡片被压成 0）
 *   - 一行 Phase 7 控件：Checkbox（可点击 toggle）+ Slider（可拖动）
 * 完全不使用 libui 的 Box/Group——libui 只提供窗口和这一块 stretchy Area。
 *
 * 事件系统（Phase 8）：
 *   - 指针：hover 高亮、按下变暗、单击 / 双击（保存按钮）
 *   - 拖动：Slider 按下并拖动可实时更新值
 *   - 键盘：Tab / Shift+Tab 在控件间移动焦点，Enter / Space 激活焦点控件
 *   - 焦点控件绘制蓝色焦点环
 * 点击按钮更新状态栏；切换主题自绘控件换色。这验证了「单画布 + 自建布局 +
 * 全自绘 + 自建事件路由」能取代 libui 容器（RendererButton 8 轮尺寸痛点的根治方案）。
 *
 * 运行： php85 examples/surface-demo.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Widgets\Surface;

$darkOverrides = [
    'color' => [
        'primary'   => [0.30, 0.65, 1.0, 1.0],
        'track'     => [0.22, 0.22, 0.24, 1.0],
        'onSurface' => [0.92, 0.92, 0.92, 1.0],
        'surface'   => [0.16, 0.16, 0.18, 1.0],
    ],
];

// Phase 7 widgets: checkbox toggle + draggable slider.
$agreeLeaf = LayoutNode::leaf(
    'agree',
    new CheckboxSpec(label: '同意条款', checked: true),
    width: 170, height: 28,
);
$sliderLeaf = LayoutNode::leaf(
    'vol',
    new SliderSpec(value: 0.6),
    width: 170, height: 28,
);

// The whole self-drawn UI is one layout tree handed to a single Surface.
$tree = LayoutNode::column(gap: 16, padding: 16)
    ->child(
        LayoutNode::row(gap: 8, align: 'start')
            ->child(LayoutNode::leaf('filled', new ButtonSpec('保存', 'filled'), width: 100, height: 36))
            ->child(LayoutNode::leaf('soft', new ButtonSpec('次要', 'soft'), width: 100, height: 36))
            ->child(LayoutNode::leaf('outline', new ButtonSpec('取消', 'outline'), width: 100, height: 36))
            ->child(LayoutNode::leaf('disabled', new ButtonSpec('禁用', 'filled', enabled: false), width: 100, height: 36))
    )
    ->child(LayoutNode::leaf('card', new CardSpec(bordered: true, elevation: 0.5), width: 0, height: 120))
    ->child(
        LayoutNode::row(gap: 16, align: 'center')
            ->child($agreeLeaf)
            ->child($sliderLeaf)
    );

$surface = new Surface($tree);

$status = new Label('Tab 切换焦点 · Enter 激活 · 点击/双击按钮 · 拖动滑块 · 切主题看换色');

$surface->onClick('filled', static fn () => $status->setText('保存 被点击（Surface 自绘）'));
$surface->onClick('soft', static fn () => $status->setText('次要 被点击（Surface 自绘）'));
$surface->onClick('outline', static fn () => $status->setText('取消 被点击（Surface 自绘）'));
$surface->onDoubleClick('filled', static fn () => $status->setText('保存 被双击（Surface 自绘）'));

// Checkbox toggles its own checked state in the tree, then repaints.
$surface->onClick('agree', static function () use ($agreeLeaf, $surface, $status): void {
    $spec = $agreeLeaf->spec;
    if (! $spec instanceof CheckboxSpec) {
        return;
    }
    $agreeLeaf->spec = new CheckboxSpec(
        checked: ! $spec->checked,
        enabled: $spec->enabled,
        label: $spec->label,
        radius: $spec->radius,
    );
    $surface->redraw();
    $status->setText(($agreeLeaf->spec->checked ? '已勾选' : '已取消勾选') . ' 同意条款');
});

// Slider drag: update value from the pointer's x position within the slider rect.
$surface->onDrag('vol', static function (float $x, float $y, float $w, float $h) use ($sliderLeaf, $surface, $status): void {
    $value = max(0.0, min(1.0, $w > 0 ? $x / $w : 0.0));
    $sliderLeaf->spec = new SliderSpec(value: $value, enabled: true, pressed: true);
    $surface->redraw();
    $status->setText('音量: ' . (int) round($value * 100) . '%');
});

$themeBtn = new Button('切换主题');
$theme = 'light';
$themeBtn->onClicked(static function () use (&$theme, $surface, $darkOverrides): void {
    $theme = $theme === 'light' ? 'dark' : 'light';
    $surface->setTheme($theme === 'dark' ? $darkOverrides : []);
});

$window = new Window('Surface 自绘画布演示', 600, 480, false);
$window->setMargined(true);
$window->centered();
$window->setChild(Build::vbox(
    Build::hbox($themeBtn, Build::stretchy(new Label(''))),
    $status,
    // The Surface Area MUST be stretchy so the non-scrolling Area gets a footprint.
    Build::stretchy($surface->root()),
));

App::new()
    ->window($window)
    ->onShouldQuit(static fn (): bool => true)
    ->run();
