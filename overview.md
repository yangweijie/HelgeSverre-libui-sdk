# Image 渲染噪点修复

## 已完成
- 定位 `surface-controls-demo.php` 中左侧图片（`ImageControl::fromFile`）的渲染噪点根因：
  - `CommandExecutor::drawSampledPixels()` 逐像素 `fillRect()` 绘制图片，RLE 合并使用严格浮点相等，导致双线性采样产生的浮点噪声把纯色区域拆成大量 1×1 小矩形；libui/Cairo 的 path fill 反锯齿让小矩形边缘混入背景色，形成噪点。
- 修改 `src/Rendering/CommandExecutor.php`：
  - RLE 合并改用 8-bit 量化颜色比较（`round(c * 255)`），避免浮点误差打断 run。
  - `dstX/dstY` 按 `round()` 对齐整数设备像素，消除子像素间隙/重叠。
- 新增 `tests/ImageRendererTest.php` 回归测试，覆盖 `ImageRenderer` 注册、命令生成、参数透传、headless-safe 行为。
- 验证：`ImageRendererTest` 6 通过，`WidgetRendererTest` 21 通过，`EditControlsTest` 20 通过（含之前修复的嵌套 ScrollView 拖拽）。

## 注意事项
- 全量 `vendor/bin/pest` 在 `FieldsTest.php` 触发 `zend_mm_heap corrupted`（退出码 134），与本次 Image 修改无关，相关独立套件均通过。
- 修复后的实际显示效果需在 macOS 上运行 `php85 examples/surface-controls-demo.php` 验证。
