<?php

declare(strict_types=1);

/**
 * Surface 自绘控件全家桶演示（全部自绘组件 + 输入类可输入）
 * ──────────────────────────────────────────────────────────────────────
 * 集成了 HTML→class 列表里所有自绘组件，并验证「输入类组件能输入」：
 *   - TextField（自绘 leaf + Surface::onText，点击聚焦后可直接键入）
 *   - SearchField / Combobox（*Control::bind 已内置 onText 文本输入）
 *   - TextArea（多行输入 + 方向键移动光标）
 *   - DropdownMenu / Radio / Checkbox（点击选择 / 切换）
 *   - Breadcrumb / Pagination / List / Table / Tabs（点击导航）
 *   - Slider（拖拽）/ Progress（按钮递增）/ ScrollView（滚动条拖拽 + 方向键）
 *   - Dialog / Drawer / Sheet / Popover（叠加层，Esc / 遮罩 / 按钮关闭）
 *
 * 整棵目录被包进一个固定尺寸的 ScrollView，因此任意窗口高度下都能滚动浏览。
 * 运行： php85 examples/surface-controls-demo.php
 * 交互： 点击控件聚焦 → 直接打字（TextField/SearchField/Combobox/TextArea）；
 *        点击 ScrollView 空白处或拖右侧滚动条 / 方向键滚动目录；
 *        Tab / Shift+Tab 切换焦点；Esc 关闭叠加层；「切换主题」看换色。
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Layout\LayoutStyle;
use Yangweijie\Ui2\Rendering\DesignTokens;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ButtonSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CardSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\CheckboxSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ListRowSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\ProgressSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\RadioSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\SliderSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextAreaSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\TextFieldSpec;
use Yangweijie\Ui2\Widgets\BreadcrumbControl;
use Yangweijie\Ui2\Widgets\ComboboxControl;
use Yangweijie\Ui2\Widgets\DialogControl;
use Yangweijie\Ui2\Widgets\DrawerControl;
use Yangweijie\Ui2\Widgets\DropdownMenuControl;
use Yangweijie\Ui2\Widgets\ListControl;
use Yangweijie\Ui2\Widgets\PaginationControl;
use Yangweijie\Ui2\Widgets\PopoverControl;
use Yangweijie\Ui2\Widgets\ScrollViewControl;
use Yangweijie\Ui2\Widgets\SearchFieldControl;
use Yangweijie\Ui2\Widgets\SheetControl;
use Yangweijie\Ui2\Widgets\Surface;
use Yangweijie\Ui2\Widgets\TabControl;
use Yangweijie\Ui2\Widgets\TableControl;
use Yangweijie\Ui2\Widgets\TextAreaControl;

$darkOverrides = [
    'color' => [
        'primary'   => [0.30, 0.65, 1.0, 1.0],
        'track'     => [0.22, 0.22, 0.24, 1.0],
        'onSurface' => [0.92, 0.92, 0.92, 1.0],
        'surface'   => [0.16, 0.16, 0.18, 1.0],
        'selection' => [0.30, 0.70, 1.0, 0.20],
        'scrim'     => [0.0, 0.0, 0.0, 0.55],
    ],
];

// 整个目录使用统一的可用宽度。ScrollView 宽 820，减去垂直滚动条 gutter(12)
// 和内部 content padding(16*2)，内容区可用宽度为 776。
$CATALOG_W = 776.0;
$TITLE_H = 22.0;
$SECTION_GAP = 16.0;

// 收集所有「区块」，每个区块 = 标题 + 内容，最后整体塞进 ScrollView。
$sections = [];
$section = static function (string $title, LayoutNode $content, float $contentH) use (&$sections, $CATALOG_W, $TITLE_H, $SECTION_GAP): void {
    $colH = $TITLE_H + $SECTION_GAP + $contentH;
    $col = LayoutNode::column(gap: $SECTION_GAP, padding: 0, align: LayoutStyle::ALIGN_STRETCH, width: $CATALOG_W, height: $colH);
    $col->child(LayoutNode::leaf(null, new LabelSpec($title, size: 14.0, align: 'left'), width: $CATALOG_W, height: $TITLE_H));
    $col->child($content);
    $sections[] = $col;
};

// ── Button：filled / soft / outline / disabled ──────────────────────────────
$buttonsRow = LayoutNode::row(gap: 12, align: 'center', height: 42)
    ->child(LayoutNode::leaf('filled', new ButtonSpec('保存', 'filled'), width: 100, height: 36))
    ->child(LayoutNode::leaf('soft', new ButtonSpec('次要', 'soft'), width: 100, height: 36))
    ->child(LayoutNode::leaf('outline', new ButtonSpec('取消', 'outline'), width: 100, height: 36))
    ->child(LayoutNode::leaf('disabled', new ButtonSpec('禁用', 'filled', enabled: false), width: 100, height: 36));
$section('Buttons（按钮）', $buttonsRow, 42.0);

// ── Checkbox + Slider ──────────────────────────────────────────────────────
$agreeLeaf = LayoutNode::leaf('agree', new CheckboxSpec(label: '启用通知', checked: true), width: 180, height: 32);
$sliderLeaf = LayoutNode::leaf('vol', new SliderSpec(value: 0.6), width: 220, height: 32);
$toggleRow = LayoutNode::row(gap: 24, align: 'center', height: 36)
    ->child($agreeLeaf)
    ->child($sliderLeaf);
$section('Checkbox / Slider（勾选 / 拖拽）', $toggleRow, 36.0);

// ── Radio（自绘 leaf 组，点击选择）+ Progress ───────────────────────────────
$radioOptions = ['PHP', 'Python', 'Rust'];
$radioNodes = [];
$radioCol = LayoutNode::column(gap: 6, padding: 8, id: 'radio-group', width: 180, height: 120);
foreach ($radioOptions as $i => $label) {
    $leaf = LayoutNode::leaf("radio:{$i}", new RadioSpec(selected: $i === 0, label: $label), width: 160, height: 28);
    $radioNodes[] = $leaf;
    $radioCol->child($leaf);
}
$progressLeaf = LayoutNode::leaf('progress', new ProgressSpec(value: 0.35), width: 240, height: 22);
$progressBtn = LayoutNode::leaf('progress+', new ButtonSpec('进度 +10%', 'soft'), width: 120, height: 32);
$progressCol = LayoutNode::column(gap: 12, align: 'start', width: 240, height: 120)
    ->child($progressLeaf)
    ->child($progressBtn);
$radioProgressRow = LayoutNode::row(gap: 24, align: 'start', height: 120)
    ->child($radioCol)
    ->child($progressCol);
$section('Radio / Progress（单选 / 递增）', $radioProgressRow, 120.0);

// ── Breadcrumb / Pagination ─────────────────────────────────────────────────
$breadcrumb = new BreadcrumbControl('path', [
    ['label' => 'Home'], ['label' => 'Library'], ['label' => 'Report'],
]);
$pager = new PaginationControl('p', totalPages: 20, active: 5);
$bcRoot = $breadcrumb->root();
$bcRoot->style->width = $CATALOG_W;
$bcRoot->style->height = 28.0;
$pageRoot = $pager->root();
$pageRoot->style->width = $CATALOG_W;
$pageRoot->style->height = 40.0;
$navCol = LayoutNode::column(gap: 12, align: LayoutStyle::ALIGN_STRETCH, width: $CATALOG_W, height: 80.0)
    ->child($bcRoot)
    ->child($pageRoot);
$section('Breadcrumb / Pagination（导航 / 翻页）', $navCol, 80.0);

// ── 输入类：TextField / SearchField / Combobox / DropdownMenu ────────────────
$tfLeaf = LayoutNode::leaf('tf', new TextFieldSpec(value: '', placeholder: '输入姓名…'), width: 280, height: 34);
$search = new SearchFieldControl('q', placeholder: '搜索文件…', width: 280);
$combo = new ComboboxControl('lang', ['PHP', 'Rust', 'Go', 'Python'], value: 'PHP', width: 280);
$dropdown = new DropdownMenuControl('sort', ['名称', '大小', '修改日期'], selected: 0, width: 280);

$search->root()->style->width = 280.0;
$search->root()->style->height = 34.0;
$combo->root()->style->width = 280.0;
$combo->root()->style->height = 34.0;
$dropdown->root()->style->width = 280.0;
$dropdown->root()->style->height = 34.0;

$inputRow = static function (LayoutNode $control, string $label) use ($CATALOG_W): LayoutNode {
    return LayoutNode::row(gap: 16, align: LayoutStyle::ALIGN_CENTER, width: $CATALOG_W, height: 38.0)
        ->child($control)
        ->child(LayoutNode::leaf(null, new LabelSpec($label, size: 12.0, opacity: 0.6), width: 160.0, height: 20.0));
};

$inputCol = LayoutNode::column(gap: 12, align: LayoutStyle::ALIGN_STRETCH, width: $CATALOG_W, height: 188.0)
    ->child($inputRow($tfLeaf, 'TextField'))
    ->child($inputRow($search->root(), 'SearchField'))
    ->child($inputRow($combo->root(), 'Combobox'))
    ->child($inputRow($dropdown->root(), 'DropdownMenu'));
$section('输入类（点击后可直接打字 / 选择）', $inputCol, 188.0);

// ── List / Table / Tabs ────────────────────────────────────────────────────
$list = new ListControl('fruits', [
    ['id' => 'a', 'label' => 'Apple', 'subtitle' => '红色 · 甜'],
    ['id' => 'b', 'label' => 'Banana', 'subtitle' => '黄色 · 软'],
    ['id' => 'c', 'label' => 'Cherry', 'subtitle' => '暗红 · 酸'],
], selected: 0);
$table = new TableControl('users',
    columns: [['label' => 'Name', 'width' => 2], ['label' => 'Role', 'width' => 1.4], ['label' => 'Status', 'width' => 1]],
    rows: [
        ['cells' => ['Ada', 'Admin', '在线']],
        ['cells' => ['Linus', 'User', '离线']],
        ['cells' => ['Grace', 'Dev', '在线']],
    ],
    selected: 0,
);
$tabs = new TabControl('main', [
    ['id' => 'home', 'label' => '概览', 'content' => LayoutNode::leaf('panel-home', new CardSpec(bordered: true, elevation: 0.3), width: 760, height: 104)],
    ['id' => 'settings', 'label' => '设置', 'content' => LayoutNode::leaf('panel-settings', new CardSpec(bordered: true, elevation: 0.3), width: 760, height: 104)],
]);
$list->root()->style->width = $CATALOG_W;
$table->root()->style->width = $CATALOG_W;
$tabs->root()->style->width = $CATALOG_W;
$section('List（列表）', $list->root(), 148.0);
$section('Table（表格）', $table->root(), 140.0);
$section('Tabs（标签）', $tabs->root(), 158.0);

// ── 多行文本 + 滚动视图 ─────────────────────────────────────────────────────
$textarea = new TextAreaControl('notes', '在此输入多行文本（Enter 换行，方向键移动光标）…', width: 320, height: 150);
$svWords = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India',
    'Juliet', 'Kilo', 'Lima', 'Mike', 'November', 'Oscar', 'Papa', 'Quebec', 'Romeo', 'Sierra', 'Tango'];
$svItems = [];
foreach ($svWords as $i => $w) {
    $svItems[] = LayoutNode::leaf("sv:row:{$i}", new ListRowSpec(label: $w, subtitle: "项目 #{$i}"), width: 320, height: 30);
}
$scroll = new ScrollViewControl('panel', $svItems, width: 320, height: 150, contentHeight: count($svItems) * 30, gap: 4, padding: 4);
$editRow = LayoutNode::row(gap: 24, align: 'start', height: 150)
    ->child($textarea->root())
    ->child($scroll->root());
$section('TextArea + ScrollView（多行输入 / 滚动）', $editRow, 150.0);

// ── 叠加层：Dialog / Drawer / Sheet / Popover ──────────────────────────────
$dialog = new DialogControl('confirm', '删除项目？', '此操作不可撤销，确定要继续吗？', [
    ['id' => 'cancel', 'label' => '取消', 'variant' => 'outline'],
    ['id' => 'ok', 'label' => '删除', 'variant' => 'filled'],
]);
$drawer = new DrawerControl('drawer-menu', '导航', '左侧导航内容区域…', [
    ['id' => 'close', 'label' => '关闭', 'variant' => 'outline'],
], 300, 'left');
$sheet = new SheetControl('sheet-filters', '筛选', '选择筛选条件后点击「应用」…', [
    ['id' => 'apply', 'label' => '应用', 'variant' => 'filled'],
    ['id' => 'cancel', 'label' => '取消', 'variant' => 'outline'],
]);
$popover = new PopoverControl('pop-ctx', '操作', '确认执行此操作？', [
    ['id' => 'ok', 'label' => '确定', 'variant' => 'filled'],
    ['id' => 'cancel', 'label' => '取消', 'variant' => 'outline'],
]);
$overlayRow = LayoutNode::row(gap: 12, align: 'center', height: 42)
    ->child(LayoutNode::leaf('showDialog', new ButtonSpec('打开对话框', 'filled'), width: 140, height: 36))
    ->child(LayoutNode::leaf('openDrawer', new ButtonSpec('打开抽屉', 'soft'), width: 140, height: 36))
    ->child(LayoutNode::leaf('openSheet', new ButtonSpec('打开底部面板', 'soft'), width: 160, height: 36))
    ->child(LayoutNode::leaf('openPopover', new ButtonSpec('打开气泡', 'soft'), width: 140, height: 36));
$section('叠加层：Dialog / Drawer / Sheet / Popover', $overlayRow, 42.0);

// ── 计算 ScrollView contentHeight ──────────────────────────────────────────
$contentHeight = 0.0;
foreach ($sections as $i => $s) {
    $contentHeight += $s->style->height ?? 0.0;
    if ($i > 0) {
        $contentHeight += 12.0; // ScrollViewControl 内部 content column 的 gap
    }
}
$contentHeight += 32.0; // ScrollViewControl 内部 content padding 上下各 16

// ── 整棵目录包进固定尺寸 ScrollView（任意窗口高度下可滚动）─────────────────
$catalog = new ScrollViewControl(
    'catalog',
    $sections,
    width: 820,
    height: 820,
    contentHeight: $contentHeight,
    gap: 12,
    padding: 16,
);
$root = LayoutNode::column(gap: 0, padding: 0, align: 'center')->child($catalog->root());

$surface = new Surface($root);
$status = new Label('点击控件聚焦 · Tab 切换 · 在 TextField/SearchField/Combobox 直接打字 · 点 ScrollView 空白或拖滚动条滚动 · 切换主题看换色');

// ── 事件接线 ──────────────────────────────────────────────────────────────
$surface->onClick('filled', static fn () => $status->setText('保存 被点击'));

$surface->onClick('agree', static function () use ($agreeLeaf, $surface, $status): void {
    $s = $agreeLeaf->spec;
    if (! $s instanceof CheckboxSpec) {
        return;
    }
    $agreeLeaf->spec = new CheckboxSpec(checked: ! $s->checked, enabled: $s->enabled, label: $s->label, radius: $s->radius);
    $surface->redraw();
    $status->setText(($agreeLeaf->spec->checked ? '已勾选' : '已取消') . ' 启用通知');
});

$surface->onDrag('vol', static function (float $x, float $y, float $w, float $h) use ($sliderLeaf, $surface, $status): void {
    $value = max(0.0, min(1.0, $w > 0 ? $x / $w : 0.0));
    $sliderLeaf->spec = new SliderSpec(value: $value, enabled: true, pressed: true);
    $surface->redraw();
    $status->setText('音量: ' . (int) round($value * 100) . '%');
});

// Radio：点击选项切换选中态
foreach ($radioNodes as $i => $leaf) {
    $surface->onClick($leaf->id, static function () use ($i, $radioNodes, $radioOptions, $surface, $status): void {
        foreach ($radioNodes as $j => $n) {
            $n->spec = new RadioSpec(selected: $j === $i, label: $radioOptions[$j]);
        }
        $surface->redraw();
        $status->setText("Radio 选中: {$radioOptions[$i]}");
    });
}

// Progress：按钮递增
$surface->onClick('progress+', static function () use ($progressLeaf, $surface, $status): void {
    $cur = $progressLeaf->spec instanceof ProgressSpec ? $progressLeaf->spec->value : 0.0;
    $next = min(1.0, $cur + 0.1);
    $progressLeaf->spec = new ProgressSpec(value: $next);
    $surface->redraw();
    $status->setText('进度: ' . (int) round($next * 100) . '%');
});

// TextField（自绘）：点击聚焦后直接键入
$surface->onText('tf', static function (string $char, bool $backspace) use ($tfLeaf, $surface, $status): void {
    $cur = $tfLeaf->spec instanceof TextFieldSpec ? $tfLeaf->spec->value : '';
    $next = $backspace ? mb_substr($cur, 0, -1) : $cur . $char;
    $tfLeaf->spec = new TextFieldSpec(value: $next, placeholder: '输入姓名…');
    $surface->redraw();
    $status->setText('姓名: ' . $next);
});

$list->bind($surface)->onSelect(static function (int $i, string $label) use ($status): void {
    $status->setText("列表选中 #{$i}: {$label}");
});
$table->bind($surface)->onSelect(static function (int $i) use ($status): void {
    $status->setText('表格选中第 ' . ($i + 1) . ' 行');
});
$tabs->bind($surface)->onChange(static function (int $i) use ($status): void {
    $status->setText('切换到标签 #' . $i);
});
$breadcrumb->bind($surface)->onNavigate(static function (int $i) use ($status): void {
    $status->setText('导航到面包屑 #' . $i);
});
$pager->bind($surface)->onChange(static function (int $page) use ($status): void {
    $status->setText('翻到第 ' . $page . ' 页');
});

$search->bind($surface)->onChange(static function (string $v) use ($status): void {
    $status->setText('搜索: ' . ($v === '' ? '(空)' : $v));
});
$combo->bind($surface)->onChange(static function (string $v) use ($status): void {
    $status->setText('Combobox: ' . $v);
});
$dropdown->bind($surface)->onSelect(static function (int $i, string $label) use ($status): void {
    $status->setText("Dropdown 选择: {$label}");
});

$textarea->bind($surface)->onChange(static function (string $v) use ($status): void {
    $status->setText('文本域: ' . mb_strlen($v) . ' 字符');
});
$scroll->bind($surface);
$catalog->bind($surface);

$dialog->bind($surface)->onClose(static function (string $id) use ($status): void {
    $status->setText($id === 'ok' ? '对话框：已确认删除' : '对话框：已取消');
});
$drawer->bind($surface)->onClose(static function (string $id) use ($status): void {
    $status->setText('Drawer 关闭: ' . ($id === '' ? 'Esc/遮罩' : $id));
});
$sheet->bind($surface)->onClose(static function (string $id) use ($status): void {
    $status->setText('Sheet 关闭: ' . ($id === '' ? 'Esc/遮罩' : $id));
});
$popover->bind($surface)->onClose(static function (string $id) use ($status): void {
    $status->setText('Popover 关闭: ' . ($id === '' ? 'Esc/遮罩' : $id));
});

$surface->onClick('showDialog', static fn () => $dialog->open());
$surface->onClick('openDrawer', static fn () => $drawer->open());
$surface->onClick('openSheet', static fn () => $sheet->open());
$surface->onClick('openPopover', static fn () => $popover->open());

// ── 原生主题切换按钮 ──────────────────────────────────────────────────────
$themeBtn = new Button('切换主题');
$theme = 'light';
$themeBtn->onClicked(static function () use (&$theme, $surface, $darkOverrides): void {
    $theme = $theme === 'light' ? 'dark' : 'light';
    $surface->setTheme($theme === 'dark' ? $darkOverrides : []);
});

$window = new Window('Surface 自绘控件全家桶', 880, 940, false);
$window->setMargined(true);
$window->centered();
$window->setChild(Build::vbox(
    Build::hbox($themeBtn, Build::stretchy(new Label(''))),
    $status,
    Build::stretchy($surface->root()),
));

if (! defined('SURFACE_DEMO_SKIP_RUN')) {
    App::new()
        ->window($window)
        ->onShouldQuit(static fn (): bool => true)
        ->run();
}

return $surface;
