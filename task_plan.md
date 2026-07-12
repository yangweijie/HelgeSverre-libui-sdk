# 海贼王·斗地主 (One Piece Doudizhu) — 任务计划

> 项目路径：`/Volumes/data/git/php/HelgeSverre-libui-sdk/`
> 技术栈：PHP 8.5 + libui (FFI) 桌面 GUI + 内置 AI + 程序化 WAV 音效
> 角色：Senior Developer (高级开发工程师) — 全栈 / 桌面 GUI / 高级 CSS / Three.js

## 目标
打造一款融合「三国百将牌」武将技能机制的**海贼王主题斗地主**，带桌面 GUI、内置 AI 对手、音效与托管（自动出牌）功能，跨 macOS / Windows / Linux 运行。

## 阶段状态

| Phase | 内容 | 状态 | 备注 |
|-------|------|------|------|
| 1 | 游戏核心设计：牌组、武将、技能机制 | ✅ complete | 融合 三国百将牌 |
| 2 | 核心逻辑：发牌 / 叫地主 / 出牌 / 压牌 / 结算 | ✅ complete | `src/Games/OnePieceDoudizhu/Game.php` |
| 3 | 程序化 WAV 音效（出牌/炸弹/胜负） | ✅ complete | `scripts/gen-sfx.php` → `assets/audio/` |
| 4 | GUI 框架：选将界面 + 对战界面 + 出牌区 | ✅ complete | `GameController.php` (1357 行) |
| 5 | 测试：Pest 回归（17 项，含 hit-test / 托管） | ✅ complete | `tests/OnePieceDoudizhuTest.php` |
| 6 | GUI 运行时修复（详见下方 Errors） | ✅ complete | 多轮截图驱动修复 |
| 7 | 跨平台字体 + 窗口居中 + 布局优化 | ✅ complete | `uiFontFamily()` + `$window->centered()` |
| 8 | 结算画面居中对齐（区域中心精确居中） | ✅ complete | `drawOver()` x=W*0.25, w=W*0.5 |
| 9 | 出牌日志：位置 / 截断 / 乱码修复 | ✅ complete | `mb_substr` 修复乱码 |
| 10 | 托管（auto-play）功能 | ✅ complete | 延迟 1s、跨局重置 |
| 11 | 拖拽选牌（长按连选 / 连消） | ✅ complete | `onClick` 重写 + `applyDrag`/`endDrag`，19 项测试全过 |

## 关键决策 / 约定
- **类命名空间**：`Brush`=`Libui\Draw\Brush`、`Color`=`Libui\Color`、`FontDescriptor`=`Libui\Text\FontDescriptor`
- **主循环**：`App::run()` 内部用 `Ffi::main()` 而非 `Loop::run()` → `Loop::isRunning()` 永远 false → 不能用 `Loop::delay` 在回调里排程，改用 `Loop::defer` + `$done` 双防
- **绘制文本对齐**：`DrawTextAlign::Center` 在 `fillRect` 全屏遮罩后上下文中失效 → 用 `x = W*0.25, width = W*0.5` + Center 实现区域精确居中
- **UTF-8 文本截断**：libui `drawString` 接收非法 UTF-8 字节序列会渲染成乱码（♣♣ 等）→ 必须用 `mb_substr(..., 'UTF-8')` 而非 `substr`
- **日志位置**：放在对手面板下方 / 出牌区域上方（`handTop - 80` 一带），避免与手牌或出牌区重叠
- **出牌区回退**：`Game::advanceTurn()` 连续 2 次 pass 后 `lastPlay=null` → 新增 `$lastShownPlay` 保存快照，`drawPlayArea()` 回退显示

## 并行工作流（独立子系统，非 Doudizhu）
以下三个工作流有独立的 `.planning/` 子目录规划文件，已完成但未 commit：

| 工作流 | 规划路径 | 状态 | 核心文件 |
|--------|---------|------|---------|
| 图表组件 | `.planning/2026-07-11-chart-component/` | 10/10 ✅ | `src/Charts/`, `tests/ChartTest.php`, `examples/chart-demo.php` |
| 渲染引擎 | `.planning/2026-07-11-rendering-engine/` | 3/3 ✅ | `src/Rendering/`（RenderCommand, DesignTokens, WidgetRenderer），26 测试 |
| 布局引擎+自绘控件 | `.planning/2026-07-11-layout-engine/` | 7/7 ✅ | `src/Layout/`, `src/Events/`, `src/Semantics/`, `src/Widgets/Surface.php` |

## Pending / 可选优化（待用户反馈）
- 暂无明确待办；等待用户运行 `php85 examples/onepiece-doudizhu.php` 截图验证
- 潜在：卡牌花色符号在某些字体的渲染对齐、更多武将技能平衡

## Errors Encountered (历史修复记录)
| 现象 | 根因 | 解决 |
|------|------|------|
| 选将界面卡牌不可见 | `Libui\Draw\FontDescriptor` 命名空间错 | → `Libui\Text\FontDescriptor` |
| `Class "Libui\Draw\Color" not found` | Color 命名空间错 + cancelTimers 私有 | → `Libui\Color`；cancelTimers 改 public |
| 叫完地主后无响应 | `Loop::isRunning()` 永远 false | 移除 guard，改用 `Loop::defer` |
| 日志区显示原始 JSON | `onGameEvent` 直接复制 `game->log` | 重写为 `formatLogLine()` 中文 |
| 点牌偏移选错 | 重叠卡牌 z-order 未处理 | 新增 `hitTopmost()` 反向遍历 |
| AI 不跟牌 | `Loop::delay` 回调上下文不可靠 | → `Loop::defer` + `$done` |
| 双数牌「10」显示成「1」 | 卡牌宽度 58 太窄 | → 68px |
| 结算文字偏左 | Center 在 fillRect 后失效 | x=W*0.25, w=W*0.5 |
| 出牌区空白 | pass×2 清 lastPlay | 新增 lastShownPlay 回退 |
| 日志乱码（♣♣） | `substr` 劈开 UTF-8 多字节 | → `mb_substr(...,'UTF-8')` |

## 扩展子系统

| Phase | 内容 | 状态 | 备注 |
|-------|------|------|------|
| A | DesignTokens 扩展：font family/size/weight/lineHeight, spacing, stroke, elevation + 15 个 renderer 迁移 | ✅ complete | `src/Rendering/DesignTokens.php` |
| B | CLI 工具链：`bin/ui2` — 10 个子命令（build:phar/build:binary/check/init/info/list 等） | ✅ complete | `bin/ui2` + composer.json bin |
| C | 快照测试：轻量 JSON 快照机制 + 3 个基线（DesignTokens/SystemInfo） | ✅ complete | `tests/Helpers/Snapshot.php` + `tests/__snapshots__/` |
| D | Capability 守卫系统：Capability 接口/注册表 + 5 个原生能力实现 | ✅ complete | `src/System/Capability*.php` × 7 |
| E | ImageControl / AvatarControl GD 像素提取修复（R/B 交换 + alpha 反转 + imagealphablending） | ✅ complete | `src/Widgets/ImageControl.php`, `src/Widgets/AvatarControl.php` |
| F | Demo 更新：fromFile/fromPng 演示 | ✅ complete | `examples/surface-controls-demo.php` |
