<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Widgets\TreeView;

$window = new Window('TreeView — PHP ↔ WebView 通信测试', 700, 450, true);

$statusLabel = new Label('点击右侧树节点，这里实时显示选中值');
$eventLog    = new Label('事件日志：');

// ── 右侧 TreeView ──
$initialData = [
    ['label' => 'src', 'icon' => 'folder', 'children' => [
        ['label' => 'index.php', 'icon' => 'code'],
        ['label' => 'style.css', 'icon' => 'file'],
        ['label' => 'app.js', 'icon' => 'code'],
        ['label' => 'images', 'icon' => 'folder', 'children' => [
            ['label' => 'logo.png', 'icon' => 'image'],
            ['label' => 'bg.jpg', 'icon' => 'image'],
        ]],
    ]],
    ['label' => 'vendor', 'icon' => 'folder', 'children' => [
        ['label' => 'autoload.php', 'icon' => 'code'],
    ]],
    ['label' => 'composer.json', 'icon' => 'file'],
    ['label' => 'README.md', 'icon' => 'file'],
];

$tree = new TreeView($window, 260, 0, 420, 340, $initialData);

// ── 事件：点击节点（WebView → PHP）──
$tree->onNodeClick(function (string $path, array $node) use ($statusLabel, $eventLog): void {
    $label = $node['label'] ?? '?';
    $icon  = $node['icon'] ?? 'default';
    $statusLabel->setText("✅ 选中: path={$path}  label={$label}  icon={$icon}");
    $eventLog->setText("event: nodeClick  path=\"{$path}\"");
});

// ── 事件：展开/折叠（WebView → PHP）──
$tree->onNodeToggle(function (string $path, bool $expanded, array $node) use ($eventLog): void {
    $action = $expanded ? 'expand' : 'collapse';
    $eventLog->setText("event: {$action}  path=\"{$path}\"");
});

// ── 左侧按钮（PHP → WebView）──

$updateBtn = (new Button('PHP → 更新树数据'))->onClicked(function () use ($tree, $eventLog): void {
    $newData = [
        ['label' => 'docs', 'icon' => 'folder', 'children' => [
            ['label' => 'README.md', 'icon' => 'file'],
            ['label' => 'CHANGELOG.md', 'icon' => 'file'],
        ]],
        ['label' => 'tests', 'icon' => 'folder', 'children' => [
            ['label' => 'Pest.php', 'icon' => 'code'],
            ['label' => 'FieldsTest.php', 'icon' => 'code'],
        ]],
        ['label' => 'phpunit.xml', 'icon' => 'file'],
    ];
    $tree->setData($newData);
    $eventLog->setText('PHP: setData() 已调用 — 树数据已替换');
});

$expandBtn = (new Button('PHP → 展开选中目录'))->onClicked(function () use ($tree, $eventLog): void {
    $path = $tree->getSelectedPath();
    if ($path !== null) {
        $tree->expandNode($path);
        $eventLog->setText("PHP: expandNode(\"{$path}\") 已调用");
    } else {
        $eventLog->setText('PHP: 请先点击选中一个节点');
    }
});

$collapseBtn = (new Button('PHP → 折叠选中目录'))->onClicked(function () use ($tree, $eventLog): void {
    $path = $tree->getSelectedPath();
    if ($path !== null) {
        $tree->collapseNode($path);
        $eventLog->setText("PHP: collapseNode(\"{$path}\") 已调用");
    } else {
        $eventLog->setText('PHP: 请先点击选中一个节点');
    }
});

$readBtn = (new Button('PHP → 读取选中路径'))->onClicked(function () use ($tree, $statusLabel): void {
    $path = $tree->getSelectedPath();
    $statusLabel->setText($path !== null ? "getSelectedPath() = \"{$path}\"" : 'getSelectedPath() = null');
});

$leftPanel = Build::vbox(
    new Label('PHP ↔ WebView 通信演示'),
    new Label(''),
    $updateBtn,
    $expandBtn,
    $collapseBtn,
    $readBtn,
    Build::stretchy(new Label('')),
);

$bottomBar = Build::hbox($statusLabel, $eventLog);

$window->setChild(Build::vbox(
    Build::hbox($leftPanel, Build::stretchy(new Label(''))),
    $bottomBar,
));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
