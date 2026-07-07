# 发现 / 技术笔记 (Findings)

## libui PHP SDK 关键陷阱
- **命名空间**：`Libui\Color`（非 `Libui\Draw\Color`）；`Libui\Draw\Brush`；`Libui\Text\FontDescriptor`（非 `Libui\Draw\FontDescriptor`）；`Libui\Draw\{DrawContext,StrokeParams}`
- **主循环模型**：`App::new()->window()->run()` 实际调用 `Ffi::main()`，**不是** `Loop::run()`。因此 `Loop::$running` 始终 false，`Loop::isRunning()` 在 GUI 运行时必然返回 false。→ 不要依赖它做 guard。
- **定时器排程**：在按钮回调 / 事件回调里，`Loop::delay`（`uiTimer`）不可靠；应优先 `Loop::defer`（`uiQueueMain`，保证下一主循环 tick 执行），并配 `$done` 标志防重复触发。
- **文本绘制对齐 bug**：`DrawContext::drawString($text, $font, $color, $x, $y, $width, $align)` 的 `DrawTextAlign::Center` 在已调用 `fillRect` 全屏遮罩的同一绘制上下文里**不生效**（文字贴左）。绕过方法：把 `x` 作为**锚点**而非左边界 —— 用 `x = W*0.25, width = W*0.5` + Center 实现区域精确居中。

## UTF-8 文本陷阱（关键！）
- libui 的 `drawString` 对**非法 UTF-8 字节序列**不会报错，而是渲染成乱码（方块 / 花色符号 ♣♣ 等）。
- PHP `substr()` 按**字节**截断。CJK 字符 UTF-8 占 3 字节，emoji 占 4 字节。若截断点落在字符中间 → 非法序列 → 乱码。
- **修复**：所有对显示文本的长度截断一律改用 `\mb_substr($s, 0, $n, 'UTF-8')`，按字符截断。
- 同样适用于卡牌描述 `Combo::describe()` 内部的子串处理（见 `GameController.php` line 1352）。

## 跨平台字体
- macOS：`.AppleSystemUIFont`（含中文 + ♥♦♣♠）
- Windows：`Microsoft YaHei`（雅黑，含花色符号）
- Linux：`Noto Sans CJK SC`（缺失时退回 Pango 通用 `Sans`）
- 用 `uiFontFamily()` 运行时按 `PHP_OS` 解析 + `UI_FONT` 环境变量覆盖。`if (!defined('FONT')) define(...)` 防重复定义（测试多次 include）。

## 斗地主逻辑注意点
- `Game::advanceTurn()`：连续 2 人 pass → `lastPlay = null`（新一轮首出）。GUI 需保存上一手快照 `$lastShownPlay` 回退显示。
- 武将技能（skill / arm / counter / skip / frozenSkip）通过 `Game::emit()` 事件广播，`GameController::onGameEvent` 转中文日志 + 触发音效。

## 测试
- Pest 框架；`tests/OnePieceDoudizhuTest.php`，**17 项全过**（含 z-order 命中测试、托管自动出牌测试）。
- 运行（在 `/Volumes/data/git/php/HelgeSverre-libui-sdk` 目录下）：`php85 vendor/bin/pest tests/OnePieceDoudizhuTest.php --no-coverage`
- `Sound` 类无头环境安全（禁用即静音），便于测试。
