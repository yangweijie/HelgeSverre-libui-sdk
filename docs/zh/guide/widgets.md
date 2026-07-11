# 自定义控件

## 自绘控件（基于 Area）

| 类名 | 说明 |
|---|---|
| `ToggleSwitch` | 基于 Area 的开关控件；`on('change')` 发射 `bool` |
| `StatusIndicator` | 彩色圆点指示器；`setColor()` / `setColorHex()` |
| `CircleProgressBar` | 环形进度条；`setProgress()`、`setColor()`、`setThickness()` — 现已接入 DesignTokens 主题系统 |
| `TableView` | 封装上游 `Table`，支持类型化列和数据绑定 |
| **`SvgView`** | SVG 渲染控件；解析 SVG 路径数据并通过 `Area` 绘制 — 支持矩形、圆形、椭圆、直线、折线、多边形、弧线及三次/二次贝塞尔曲线 |
| **`SvgDelegate`** | 从 `SvgView` 提取的 `AreaDelegate` — 处理 SVG 解析、命中测试、鼠标事件（悬停高亮、点击） |

```php
$toggle = new ToggleSwitch(true);
$toggle->on('change', fn (bool $on) => print($on ? '开' : '关'));

$status = new StatusIndicator(new Color(0x22, 0xC5, 0x5E));
$status->setColorHex(0xEF4444);

$bar = new CircleProgressBar(50);
$bar->setProgress(75);
$bar->setColor(new Color(0, 0.5, 1));
$bar->setThickness(16);
```

## Surface 画布控件

`Surface` 是一个**可组合的画布控件**（1056 行），基于单个 libui `Area` 构建。它将布局引擎、渲染引擎、WidgetRenderer 注册表和事件系统集成为一个可嵌入的控件，可放入任何 libui 容器（`Box`、`Form`、`Grid`、`Tab`）。

| 类名 | 说明 |
|---|---|
| `Surface` | 高级可组合画布：内部集成 `FlexLayout` + `RendererFactory` + 命令缓存 + 鼠标/键盘路由 |

```php
use Yangweijie\Ui2\Widgets\Surface;

$surface = new Surface(400, 300);
$surface->addChild('button1', ButtonRenderer::class, ['label' => '点击我']);
$surface->addChild('slider1', SliderRenderer::class, ['min' => 0, 'max' => 100, 'value' => 50]);

$container = new Box(Box::Vertical);
$container->append($surface);
```

`Surface` 控件与 libui 容器**完全可组合**——它们不基于子窗口（与 WebView 控件不同）。

### 基于 Surface 的控件

以下控件基于 `Surface` 和 `WidgetRenderer` 构建：

| 类名 | 说明 |
|---|---|
| `ButtonControl` | Surface 渲染的按钮，支持悬停/按下状态 |
| `CheckboxControl` | 自定义复选框，带标签 |
| `RadioControl` | 单选按钮，带悬停/按下状态和填充动画 |
| `SliderControl` | 水平滑块，带拖拽手柄 |
| `ProgressControl` | 进度条，支持定值填充 |
| `TextFieldControl` | 文本输入框，带光标和选区 |
| `SelectControl` | 下拉选择框 |
| `ComboboxControl` | 可搜索的组合框 |
| `BreadcrumbControl` | 导航面包屑 |
| `DialogControl` | 模态对话框容器 |
| `DrawerControl` | 侧边抽屉面板 |
| `DropdownMenuControl` | 下拉菜单 |
| `ListControl` | 可滚动的列表 |
| `PaginationControl` | 分页导航 |
| `PopoverControl` | 弹出提示 |
| `ScrollViewControl` | 可滚动内容区域 |
| `SearchFieldControl` | 带图标的搜索输入框 |
| `SheetControl` | 底部弹出面板 |
| `TabControl` | 标签切换器 |
| `TableControl` | 数据表格，带表头 |
| `TextAreaControl` | 多行文本区域 |

关于 Surface、渲染引擎和布局引擎如何协同工作的详细信息，请参见[架构文档](/zh/guide/architecture)。

## RendererButton（桥接控件）

`RendererButton` 是 libui 原生 `Button` 和自定义 WidgetRenderer 系统之间的**桥梁**。它扩展 `Composite`，底层包装真实的 libui `Button`，但外观通过 `ButtonRenderer` 使用 Surface 渲染管线绘制。

| 类名 | 说明 |
|---|---|
| `RendererButton` | 复合控件 — 底层为原生 libui Button，外观通过 ButtonRenderer + DesignTokens 自定义绘制 |

```php
use Yangweijie\Ui2\Widgets\RendererButton;

$btn = new RendererButton('主题按钮', function () {
    print('点击了！');
});
$container->append($btn);
```

## SVG 渲染

### SvgView

`SvgView` 直接在 `Area` 上渲染 SVG 路径数据——无需外部 SVG 库。它解析 `<path d="..." />`、`<rect>`、`<circle>`、`<ellipse>`、`<line>`、`<polyline>`、`<polygon>` 以及常见的变换属性，将其转换为原生 libui 绘制操作。

```php
use Yangweijie\Ui2\Widgets\SvgView;

$svg = new SvgView(
    'M10 10 L 100 10 L 100 80 Z',  // SVG 路径数据
    120, 100,                         // 视口宽度、高度
    ['fill' => '#3B82F6', 'stroke' => '#1D4ED8', 'stroke-width' => 2]
);
$container->append($svg);
```

### SvgDelegate

`SvgDelegate`（`src/Widgets/SvgDelegate.php`）是从 `SvgView` 提取的 `AreaDelegate` 实现。提供：

- **SVG 解析** — 支持 `M`、`L`、`H`、`V`、`C`、`Q`、`A`、`Z` 路径命令，以及 `<rect>`、`<circle>`、`<ellipse>`、`<line>`、`<polyline>`、`<polygon>`
- **命中测试** — 精确几何命中测试（圆：`dx²+dy² ≤ r²`，椭圆：`(dx/rx)² + (dy/ry)² ≤ 1`）
- **鼠标交互** — 通过 `EmitsEvents` trait 实现悬停高亮和点击检测
- **弧线转换** — SVG 弧线命令的端点参数化到中心参数化转换

## 原生 OS 通知

| 类名 | 说明 |
|---|---|
| `Toast` | 静态助手：`show(title, message, ?icon)` — 发送原生 OS 桌面通知 |

```php
use Yangweijie\Ui2\Widgets\Toast;

Toast::show('ui2', '文件保存成功！');
Toast::show('警告', '磁盘空间不足', '/path/to/icon.png');
```

仅一个静态方法——无需实例化。支持 macOS（通知中心）、Linux（D-Bus）和 Windows（Toast API）。

## WebView 控件

这些控件继承 `WebView`，创建无边框子窗口（参见 [WebView](/zh/guide/webview)）：

| 类名 | 说明 |
|---|---|
| `TreeView` | 可折叠的文件/对象树，支持图标、点击和切换回调 |
| `CodeEditor` | 基于 highlight.js 的代码编辑器，支持 17 种语言语法高亮 |

```php
$tree = new TreeView($window, 0, 0, 260, 400, [
    ['label' => 'src', 'icon' => 'folder', 'children' => [
        ['label' => 'index.php', 'icon' => 'code'],
        ['label' => 'style.css', 'icon' => 'file'],
    ]],
]);
$tree->onNodeClick(fn (string $path, array $node) => print("点击: {$path}"));

$editor = new CodeEditor($window, 0, 0, 600, 400, 'php', false,
    "<?php\n\necho 'hello';\n"
);
$editor->onChange(fn (string $code) => print("编辑器变更: {$code}"));
```
