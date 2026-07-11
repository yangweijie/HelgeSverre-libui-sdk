# 发现 / 技术笔记 (Findings) — 图表组件

## libui Area 输入模型（最重要约束）
- `Area` 仅转发：**draw / mouse / mouseCrossed / dragBroken / key**。**没有** wheel / touch / drag 事件。
- `mouse()` 入参 `AreaMouseEvent` 字段：`x, y, down(1=左键按下), up(1=松开), count(2=双击), modifiers(Shift=4, Ctrl=1), held(拖拽中保持按钮掩码)`。
- `key()` 入参 `AreaKeyEvent`：`key(ASCII)`, `extKey`, `modifiers`, `up`；判断用 `isKeyDown($asciiCode)`。
- 原生双击：用 `count === 2` 判双击（无需自己计时）。
- 拖拽判定：`down!==0 || held!==0` 按住；`up!==0` 松开。
- **结论**：真·双指捏合在桌面端无法直连，必须自解释手势（Shift+拖拽 / 框选 / 键盘）。

## libui 文本绘制（tooltip 排版核心坑）
- `DrawContext::drawString($text, $font, $color, $x, $y, $width=null, $align=Left)` 的 **y 是文本顶边**（top-left），**不是基线**。
- 居中正确做法：先 `TextLayout($attr, $text)` → `extents()` 得 `[$tw, $th]`；框宽=`$tw + padX*2`、框高=`$th + padY*2`；`drawString(..., $tx + padX, $ty + padY, $w, DrawTextAlign::Center)` 实现水平居中 + 上下等 padding。
- 字体视觉高度 < 字号（约 0.72×），若用 `($h - $fs)/2` 估算会偏上 —— 必须 `extents()` 实测。
- `TextLayout` 构造：`new TextLayout(new Attribute(...), $text)`；`Attribute` 可包 `FontDescriptor`（weight/italic 生效）。
- 导入：`use Libui\Text\{Attribute, AttributedString, TextLayout};`

## 坐标与绘图 API
- `DrawContext`：`fillRect / fillCircle / fillRoundedRect / fillPath / strokePath / strokeLine / strokeRect / strokeCircle / drawString / withSave / transform`。
- `Brush`：`rgb($hex)`、`color(Color)`、`linearGradient`、`radialGradient`、`rgba($hexOrInt, $alpha)`（alpha 0..1）。
- `Color`：`rgb(int)`、`rgba`、`hex`、`withAlpha`。
- `Path`：`newFigure / lineTo / addRectangle / circle / ellipse / roundedRect / wedge / polygon / end / free`。
- `StrokeParams::solid($thickness)`。
- `FontDescriptor($family, $size, $weight, $italic, $stretch)`；weight 用 `TextWeight::Bold/Medium`。
- `DrawTextAlign::Left/Center/Right`。

## Scale（nice-number 刻度）
- `Scale::forValues($values, $zeroBased)` → min/max；`ticks($count=5)` 用 nice-number 算法（niceNum）生成"好看"的刻度。
- `toPixel($v, $top, $bottom)` / `fromPixel($py, $top, $bottom)`：数据↔像素。
- `ChartView::xToPx/yToPx/pxToX/pxToY`：绘图时把 `Scale` 域映射到 plot 矩形；做 clamp 防除零。

## 动画
- `Animator::animate($from, $to, $durationMs, $onFrame, $onDone)` 用 `Loop::repeat(16, ...)` 驱动 tween。
- 缓动 `easeOutCubic($t) = 1 - (1-t)**3`；`lerp($from, $to, $e)` 对长度不齐的序列补 0。
- GUI 用 Loop 驱动；测试用 `seekTo($t)` 直接定位补间帧（确定性）。
- `Chart` 无 `Area` 绑定时 `setData()` 即时同步显示值（headless 安全）。

## 跨平台字体（沿用斗地主经验）
- macOS `.AppleSystemUIFont` / Windows `Microsoft YaHei` / Linux `Noto Sans CJK SC`；`ChartConfig::defaultFontFamily()` + `UI_FONT` 环境变量覆盖。

## 测试
- Pest；`tests/ChartTest.php` **14 项全过**（构造、setData 不丢 null、系列增减、Scale nice ticks、zoomAt 锚点居中+clamp、pan 全域 no-op、animator 缓动+补零+seekable、像素↔数据往返、工厂映射、setType 复位、zoomTo 提交+clamp、主题应用+回退、hover 初态）。
- 运行：`php85 vendor/bin/pest tests/ChartTest.php --no-coverage`
- 文档站 VuePress：`docs/**/*.md` 源码；`npm run docs:dev` 本地预览。

## 性能
- 仅在"命中目标变化"或"悬停时光标移动"才 `redraw()`，避免无谓重绘。
- tooltip 跟随光标绘制圆角半透明框 + 主题色；靠近边缘自动翻转；超出 plot 裁剪。
