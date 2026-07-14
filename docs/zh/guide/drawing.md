# 绘图

经过补丁的 `DrawContext` 提供了流畅的构建器模式进行 2D 绘图：

```php
$context->fillRect(10, 10, 100, 50, $brush);
$context->strokeCircle(60, 80, 30, $strokeParams);
$context->fillPolygon([10, 20, 30], [10, 40, 10], $brush);

// 保存/恢复变换状态
$context->withSave(function (DrawContext $ctx) {
    $ctx->translate(50, 50);
    $ctx->fillRect(0, 0, 20, 20, $brush);
});

// 测量和绘制文本
$context->drawString('你好', 10, 10, $font, $brush);
```

## 路径辅助方法

经过补丁的 `Path` 添加了便捷方法：

```php
$path->wedge(100, 100, 50, 0, M_PI_2);          // 扇形
$path->polygon([10, 50, 90], [10, 90, 10]);     // 三角形
$path->roundedRect(10, 10, 100, 50, 10);        // 圆角矩形
$path->bezierThrough([10, 40, 90], [50, 10, 50]); // 平滑曲线
```

## RenderCommand 管线

对于需要命令批量处理的结构化绘制，使用 `RenderCommand` 管线（参见[架构](/zh/guide/architecture)）：

```php
use Yangweijie\Ui2\Rendering\RenderCommandList;
use Yangweijie\Ui2\Rendering\CommandExecutor;

$cmds = (new RenderCommandList())
    ->begin()
    ->addBoxShadow(2, 2, 8, [0, 0, 0, 0.2])
    ->addFill(0x3B82F6)
    ->addRoundedRect(10, 10, 100, 50, 8)
    ->addDrawString('Hello', 10, 30, $font, 0xFFFFFF)
    ->end();

CommandExecutor::execute($drawContext, $cmds->getCommands());
```

这是 `WidgetRenderer` 系统的基础——详见[渲染引擎](/zh/guide/architecture#渲染引擎-srcrendering)。

## CanvasSpec — Surface 中嵌入自定义绘制

`CanvasSpec` 将任意 `DrawContext` 绘制回调嵌入 Surface 的 `LayoutNode` 树中。这让你可以在同一个 Surface 中与标准控件（标签、按钮、滑块）一起绘制图表、游戏、自定义可视化等内容。

### 用法

```php
use Yangweijie\Ui2\Rendering\WidgetRenderer\CanvasSpec;
use Yangweijie\Ui2\Rendering\WidgetRenderer\LabelSpec;
use Yangweijie\Ui2\Layout\LayoutNode;
use Yangweijie\Ui2\Widgets\Surface;

$chart = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));
        // 绘制折线图、柱状图、游戏区域等
        foreach ($data as $i => $v) {
            $x = ($i / (count($data) - 1)) * $w;
            $y = $h - ($v / $max) * $h;
            $ctx->fillCircle($x, $y, 4.0, Brush::rgb(0x3B82F6));
        }
    },
    background: 0x1E293B,  // 可选：在回调之前填充背景色
);

$layout = LayoutNode::column(gap: 8, padding: 12)
    ->child(LayoutNode::leaf('title', new LabelSpec('图表'), height: 30.0))
    ->child(LayoutNode::leaf('canvas', $chart, height: 200.0))
    ->child(LayoutNode::leaf('footer', new LabelSpec('底部文本'), height: 20.0));

$surface = new Surface($layout);
```

### 工作原理

1. `CanvasSpec` 持有一个 `\Closure(DrawContext, float, float): void` 回调
2. `CanvasRenderer` 将其包装为 `DrawCallback` 渲染命令
3. `CommandExecutor::dispatch()` 用 DrawContext 和分配的宽高调用回调
4. Surface 的 `withSave()` + `transform()` 将 DrawContext 平移到节点坐标，因此 `(0, 0)` = 节点分配矩形的左上角

### 要点

- **无需修改 Surface** — CanvasSpec 是纯数据层扩展
- **回调接收已变换的 DrawContext** — 在 `(0, 0)` 处绘制即可填充节点区域
- **与任何 Spec 混合** — CanvasSpec 叶子节点可与 LabelSpec、ButtonSpec 等在同一棵树中共存
- **背景填充可选** — 传 `background: 0xRRGGBB` 或 `null` 表示透明
- **类型**: `\Closure`，非 `callable` — PHP 8.5 不允许 `callable` 作为 readonly 属性类型

### 示例：动画进度条

```php
$progress = 0.0;

$bar = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h) use (&$progress): void {
        $barH = 16.0;
        $y = ($h - $barH) / 2.0;

        $ctx->fillRect(0, $y, $w, $barH, Brush::rgb(0x1E293B));  // 轨道
        $ctx->fillRect(0, $y, $w * $progress, $barH, Brush::rgb(0x3B82F6));  // 填充
    },
);

// 动画
Loop::repeat(50, function () use (&$progress, $surface): bool {
    $progress += 0.01;
    if ($progress > 1.0) $progress = 0.0;
    $surface->redraw();
    return true;
});
```
