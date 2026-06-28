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
