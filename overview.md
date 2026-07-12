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

# DSL Counter 布局重叠修复

## 已完成
- 定位 `surface-controls-demo.php` 中 DSL Counter 卡片内容重叠的根因：
  - `counter.native` 里 `Card`/`Column` 高度仅 `108`，内部 padding + gap + 三个 Label + 一个 Row 的实际需求远超该高度，导致 `Row` 被 FlexLayout 压缩；但 `Row` 内的 `Button` 有固定宽高，于是按钮溢出并与文本重叠。
  - `Row` 与 `Label` 未指定 `width`，在 `align="center"` 的 `Column` 中 cross size 被算为 `0`，进一步导致按钮坐标/宽度异常、Label 文本布局宽度为 `0`。
- 修改 `examples/counter.native`：
  - `Card`/`Column` 高度从 `108` 调整为 `176`。
  - 给三个 `Label` 增加明确的 `width`/`height`（`328×18`、`328×32`、`328×14`）。
  - 给 `Row` 增加 `width="192"`（三个按钮 44+44+80 + 两个 gap 12）。
- 修改 `examples/surface-controls-demo.php`：
  - 包裹 DSL Counter 的演示 `Row` 高度从 `108.0` 同步改为 `176.0`。
  - 关键补充：section 注册的 `contentH` 从 `108.0` 同步改为 `188.0`（比 Card 高 12px），让 Card 底部到 section 底部保留 12px 间距；再加上 ScrollView content gap 12px，使 Card 底部（含 2px elevation 阴影）到下一个 section 标题之间仍有约 22px 净间距，避免视觉上贴紧/重叠。
- 验证：headless 布局计算确认 DSL Counter Card 底部到 Breadcrumb 标题顶部间距 24px（含阴影后约 22px），无重叠；`WidgetRendererTest`、`RenderingTest`、`EditControlsTest`、`ImageRendererTest` 均通过。
