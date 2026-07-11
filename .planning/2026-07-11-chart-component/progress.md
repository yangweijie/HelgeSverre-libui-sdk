# Progress Log — 图表组件

## Session: 2026-07-11（图表组件全程 + 收尾打磨 + 文档）

### 本次完成（汇总自本会话全部工作）
- [x] **核心数据模型**（Phase 1）：`Dataset` / `ChartType`(Line/Bar/Pie/Doughnut/Scatter) / `Scale`(nice-number) / `ZoomState` / `Animator` / `ChartConfig` / `ChartView`。
- [x] **渲染器体系**（Phase 2）：`ChartRenderer` 接口 + `CartesianRenderer`(网格/轴/图例/折线/柱状公共逻辑) → `LineRenderer`/`BarRenderer`；`PieRenderer`(饼/环)；`RendererFactory` 单例注册。
- [x] **Chart 组件**（Phase 3）：`Chart extends AreaDelegate`，`draw/mouse/key`，手势分流 + 动画数据更新。
- [x] **演示 + 测试**（Phase 4）：`examples/chart-demo.php`（920×640，顶栏多按钮 + 状态提示）；`tests/ChartTest.php` 14 项全过。
- [x] **拖拽交互修复**（Phase 5）：诊断 `pan()` 全域 no-op → 改为 box-zoom / pan / pinch 分流；`ZoomState::zoomTo()` 提交选框；`draw()` 画半透明选框。
- [x] **柱状数值标签 + 悬停 tooltip + 主题**（Phase 6）：
  - 负值标签落柱底（`maybeValueLabel` 的 `below` 参数）；demo "数值标签" 切换按钮。
  - `ChartView` 新增 `barHitboxes`/`points`/`pieCenter|pieRadius|pieInner|pieSlices`；`Chart::mouse()` 悬停分支（柱优先，否则 ≤16px 最近点；饼按半径+角度命中）；`drawTooltip` 圆角半透明框 + 高亮（柱白描边 / 点白圆环 / 扇区 0.28 叠加）。
  - `ChartConfig::THEMES` 内置 light/dark（含 tooltip 配色）；`Chart::setTheme()`；demo "主题" 按钮。
- [x] **tooltip 文字排版打磨**（Phase 7，多轮截图驱动）：
  - 用 `TextLayout::extents()` 实测文字宽高，`drawString(... Center, width=$w)` 水平居中；框宽=`$tw+16`、框高=`$th+8`、y=`框顶+4` 上下等 padding。
  - 关键认知：libui `drawString` 的 y 是**文本顶边**，非基线（前几版偏上/偏下根因）。
- [x] **文档**（Phase 8）：`docs/zh/guide/chart.md` + `docs/en/guide/chart.md`；`docs/.vuepress/config.ts` 中英文侧边栏加「图表 / Chart」；`docs/zh|en/examples.md` 加 `chart-demo.php`；根 `README.md` Drawing 章加 Chart 小节、Examples 补一行。

### 验证
- `php85 -l` 全部源文件 + demo 干净。
- `php85 vendor/bin/pest tests/ChartTest.php --no-coverage` → **14 passed**。
- headless smoke：构造 `Chart` + `setData` + `setTheme` + `getHover` 不致命。
- 文档为 draw 路径（tooltip/主题/高亮）需 GUI 才能肉眼验证；本沙箱无显示器，未能替用户开窗口看。

### 待用户验证（GUI 路径）
- 跑 `php85 examples/chart-demo.php` 看：① 未放大拖拽出蓝框、松手放大 ② 放大后拖拽平移 ③ 悬停 tooltip 文字居中、不超框 ④ 数值标签/主题按钮切换。

### 关键错误修复（见 task_plan.md Errors 表）
- 拖拽平移 no-op → box-zoom 分流。
- tooltip 文字多轮偏上/偏下 → `TextLayout::extents()` 顶边对齐。
- `redraw()` 改 public、`ChartRenderer.php` 改名、`toBeApproximately`/`toBeBetween` 替换。

---
*规划文件遵循 planning-with-files 技能：task_plan.md=阶段追踪，findings.md=技术发现，progress.md=会话日志。本 plan 独立于 One Piece 斗地主 plan（`.planning/2026-07-06-onepiece-doudizhu`）。*
