<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/**
 * ui2 DevTools 检查器 — 一键演示
 * ──────────────────────────────────────────────────────────────────────
 * 启动一个内置示例窗口（多种自绘组件），并自动挂载 ComponentInspector +
 * InspectorServer。随后在终端打印浏览器面板地址，用普通浏览器打开即可像
 * F12 一样检查 / 实时编辑窗口内的自绘组件。
 *
 * 运行方式：
 *   php examples/inspect-demo.php                 # 默认端口 7711
 *   php examples/inspect-demo.php --port=7712     # 指定端口
 *   INSPECT_PORT=7712 php examples/inspect-demo.php
 *
 * 该文件同时被 `bin/ui2 inspect` 子命令复用（见 bin/ui2）。
 */

use Libui\App;
use Libui\Build;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Rendering\WidgetRenderer\BadgeSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\System\ComponentInspector;
use Yangweijie\Ui2\System\InspectorServer;
use Yangweijie\Ui2\Widgets\Surface;

/**
 * Build and run the demo window with the inspector wired in.
 *
 * @param int<1,65535> $port HTTP port for the inspector panel.
 */
function runInspectDemo(int $port = 7711): void
{
    if (!extension_loaded('ffi')) {
        fwrite(STDERR, "错误：运行 GUI 检查器需要 ext-ffi。\n");
        fwrite(STDERR, "请确认 php.ini 中已启用 extension=ffi，且 ffi.enable=preload 或 ffi.enable=true。\n");
        exit(1);
    }

    // --- 一个覆盖多种组件类型的示例树 ---
    $tree = LayoutNode::column(gap: 16, padding: 16)
        ->child(
            LayoutNode::row(gap: 10)
                ->child(LayoutNode::leaf('save', new ButtonSpec('保存', 'filled'), width: 110, height: 36))
                ->child(LayoutNode::leaf('cancel', new ButtonSpec('取消', 'outline'), width: 110, height: 36))
                ->child(LayoutNode::leaf('disabled_btn', new ButtonSpec('禁用', 'filled', enabled: false), width: 110, height: 36))
                ->child(LayoutNode::leaf('badge', new BadgeSpec('NEW', 'accent'), width: 60, height: 36))
        )
        ->child(
            LayoutNode::leaf(
                'title',
                new LabelSpec('ui2 DevTools 检查器演示', 18.0, 'bold'),
                width: 0, height: 28,
            )
        )
        ->child(
            LayoutNode::leaf(
                'card',
                new CardSpec(bordered: true, elevation: 0.5),
                width: 0, height: 120,
            )
        )
        ->child(
            LayoutNode::row(gap: 24, align: 'center')
                ->child(LayoutNode::leaf('agree', new CheckboxSpec(label: '同意条款', checked: true), width: 150, height: 28))
                ->child(LayoutNode::leaf('volume', new SliderSpec(value: 0.6), width: 200, height: 28))
        )
        ->child(
            LayoutNode::leaf('loading', new ProgressSpec(value: 0.4), width: 0, height: 24)
        );

    $surface = new Surface($tree);

    $status = new Label('DevTools 已启用：在浏览器面板开启「拾取模式」后，点击窗口内组件即可选中');

    $surface->onClick('save', static fn () => $status->setText('保存 被点击'));
    $surface->onClick('cancel', static fn () => $status->setText('取消 被点击'));
    $surface->onDrag('volume', static function (float $x, float $y, float $w, float $h) use ($surface, $status): void {
        $value = $w > 0 ? max(0.0, min(1.0, $x / $w)) : 0.0;
        $leaf = LayoutNode::find($surface->rootLayout(), 'volume');
        if ($leaf !== null && $leaf->spec instanceof SliderSpec) {
            $leaf->spec = new SliderSpec(value: $value, enabled: true, pressed: true);
            $surface->redraw();
            $status->setText('音量: ' . (int) round($value * 100) . '%');
        }
    });

    $window = new Window('ui2 DevTools 检查器示例', 720, 540, false);
    $window->setMargined(true);
    $window->centered();
    $window->setChild(Build::vbox(
        Build::hbox($status),
        Build::stretchy($surface->root()),
    ));

    // 绑定检查器（覆盖回调到本 Surface 的钩子）
    $inspector = $surface->enableInspector(720.0, 540.0);
    $server = new InspectorServer($inspector);

    $panelPath = realpath(__DIR__ . '/../inspector-panel/index.html')
        ?: __DIR__ . '/../inspector-panel/index.html';

    $app = App::new()->window($window);
    $app->afterInit(static function () use ($server, $port, $panelPath): void {
        $server->start($port);
        $bound = $server->port();
        fwrite(STDOUT, "\n=== ui2 DevTools Inspector ===\n");
        fwrite(STDOUT, "浏览器面板已就绪，请打开：\n");
        fwrite(STDOUT, "  file://{$panelPath}\n");
        fwrite(STDOUT, "连接端口：{$bound}" . ($bound !== $port ? " （请求 {$port} 被占用，已自动换绑）" : "") . "\n");
        fwrite(STDOUT, "在面板中点击「连接」，开启「拾取模式」后在窗口内点击组件即可选中。\n");
        fwrite(STDOUT, "Ctrl+C 退出。\n\n");
    });
    $app->onShouldQuit(static fn (): bool => true);
    $app->run();
}

// 仅当作为入口脚本直接执行时才自动运行（被 bin/ui2 inspect 复用时不应重复运行）。
if (realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $port = (int) ($_SERVER['INSPECT_PORT'] ?? 7711);
    foreach ($argv as $a) {
        if (preg_match('/^--port=(\d+)$/', $a, $m)) {
            $port = (int) $m[1];
        }
    }
    runInspectDemo($port);
}
