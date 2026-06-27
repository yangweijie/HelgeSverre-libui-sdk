<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\Ffi;
use Libui\Label;
use Libui\Loop;
use Libui\Window;
use Yangweijie\Ui2\Widgets\TreeView;

Ffi::init();

$window = new Window('TreeView — PHP ↔ WebView 通信测试', 700, 450, true);

$statusLabel = new Label('点击右侧树节点，这里实时显示选中值');
$eventLog    = new Label('事件日志：');

$updateBtn = (new \Libui\Button('PHP → 更新树数据'));
$expandBtn = (new \Libui\Button('PHP → 展开选中目录'));
$collapseBtn = (new \Libui\Button('PHP → 折叠选中目录'));
$readBtn = (new \Libui\Button('PHP → 读取选中路径'));

$leftPanel = \Libui\Build::vbox(
    new Label('PHP ↔ WebView 通信演示'),
    new Label(''),
    $updateBtn,
    $expandBtn,
    $collapseBtn,
    $readBtn,
    \Libui\Build::stretchy(new Label('')),
);

$bottomBar = \Libui\Build::hbox($statusLabel, $eventLog);

$window->setChild(\Libui\Build::vbox(
    \Libui\Build::hbox($leftPanel, \Libui\Build::stretchy(new Label(''))),
    $bottomBar,
));

$window->setMargined(true);
$window->show();

[$contentW, $contentH] = $window->getContentSize();

$treeX = 260;
$treeY = 0;
$treeW = \max(200, $contentW - $treeX);
$treeH = \max(200, $contentH - 30);

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

$tree = new TreeView($window, $treeX, $treeY, $treeW, $treeH, $initialData);

$tree->onNodeClick(function (string $path, array $node) use ($statusLabel, $eventLog): void {
    $label = $node['label'] ?? '?';
    $icon  = $node['icon'] ?? 'default';
    $statusLabel->setText("选中: path={$path}  label={$label}  icon={$icon}");
    $eventLog->setText("event: nodeClick  path=\"{$path}\"");
});

$tree->onNodeToggle(function (string $path, bool $expanded, array $node) use ($eventLog): void {
    $action = $expanded ? 'expand' : 'collapse';
    $eventLog->setText("event: {$action}  path=\"{$path}\"");
});

$updateBtn->onClicked(function () use ($tree, $eventLog): void {
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

$expandBtn->onClicked(function () use ($tree, $eventLog): void {
    $path = $tree->getSelectedPath();
    if ($path !== null) {
        $tree->expandNode($path);
        $eventLog->setText("PHP: expandNode(\"{$path}\") 已调用");
    } else {
        $eventLog->setText('PHP: 请先点击选中一个节点');
    }
});

$collapseBtn->onClicked(function () use ($tree, $eventLog): void {
    $path = $tree->getSelectedPath();
    if ($path !== null) {
        $tree->collapseNode($path);
        $eventLog->setText("PHP: collapseNode(\"{$path}\") 已调用");
    } else {
        $eventLog->setText('PHP: 请先点击选中一个节点');
    }
});

$readBtn->onClicked(function () use ($tree, $statusLabel): void {
    $path = $tree->getSelectedPath();
    $statusLabel->setText($path !== null ? "getSelectedPath() = \"{$path}\"" : 'getSelectedPath() = null');
});

$window->onClosing(function () {
    Ffi::quit();
    return true;
});

Ffi::onShouldQuit(fn () => true);

$tree->autoResize($window, $treeX, $treeY);
$tree->cleanupOnClose($window);

Loop::run();
