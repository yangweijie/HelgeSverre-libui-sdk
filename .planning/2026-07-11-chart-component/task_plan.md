# 图表组件 (Chart.js-style, libui Area 自绘) — 任务计划

> 项目路径：`/Volumes/data/git/php/HelgeSverre-libui-sdk/`
> 技术栈：PHP 8.5 + libui (FFI) 桌面 GUI + `DrawContext` 纯自绘
> 角色：Senior Developer (高级开发工程师) — 全栈 / 桌面 GUI / 高级 CSS / Three.js
> 对应需求：参考 Chart.js 设计，基于 libui `Area` 自绘的图表组件，零第三方图表库。

## 目标
实现一套 Chart.js 风格的图表组件，覆盖：折线/柱状/饼图/环形/散点 5 种类型；纯 `Area` 自绘；手势缩放（双击/捏合/框选/平移/键盘）；动态数据 + 动画过渡；坐标轴/图例/网格/数据标签；可配置 + 可扩展。并在此基础上补齐柱状数值标签、悬停 tooltip、明/暗主题，以及 tooltip 文字排版打磨。

## 阶段状态（全部完成 ✅）

### Phase 1: 核心数据模型
- [x] `Dataset` / `ChartType`(Line/Bar/Pie/Doughnut/Scatter) / `Scale`(nice-number) / `ZoomState` / `Animator` / `ChartConfig` / `ChartView`
- **Status:** complete

### Phase 2: 渲染器体系
- [x] `ChartRenderer` 接口 + `CartesianRenderer`(抽象, 网格/轴/图例/折线/柱状公共逻辑) → `LineRenderer`/`BarRenderer`；`PieRenderer`(饼/环)；`RendererFactory` 单例注册
- **Status:** complete

### Phase 3: Chart 组件（Area 自绘 + 交互）
- [x] `Chart extends AreaDelegate`：`draw/mouse/key`，手势分流 + 动画数据更新
- **Status:** complete

### Phase 4: 演示 + 测试
- [x] `examples/chart-demo.php`（920×640, 顶栏多按钮 + 状态提示）；`tests/ChartTest.php` 14 项全过
- **Status:** complete

### Phase 5: 拖拽交互修复（框选放大）
- [x] 诊断 `pan()` 全域 no-op → 改 box-zoom / pan / pinch 分流；`ZoomState::zoomTo()`；`draw()` 画半透明选框
- **Status:** complete

### Phase 6: 柱状数值标签 + 悬停 tooltip + 主题
- [x] 负值标签落柱底；hover 命中盒（柱/点/扇区）；light/dark 主题预设 + `setTheme()`
- **Status:** complete

### Phase 7: tooltip 文字垂直/水平居中打磨
- [x] 用 `TextLayout::extents()` 实测尺寸，绕开 baseline 猜测（顶边对齐 + 居中）
- **Status:** complete

### Phase 8: 文档
- [x] `docs/zh|en/guide/chart.md` + 侧边栏注册 + README/Examples 更新
- **Status:** complete

### Phase 9: Color::lerp 应用 —— 明暗变体多系列调色板 + 主题切换颜色补间动画
- [x] `ChartConfig`：`colorAt($i)` 超出基础 10 色后按基础色 HSL **亮度**自动生成明暗变体（交替更亮/更暗、`paletteVariantStep` 默认 0.13 递进），解决 >10 系列撞色；新增 `seriesPalette($count)`、`paletteVariantStep(float)`；`interpolateTheme($a,$b,$t)` 逐字段 `Color::rgb()->lerp()->toHex()`。
- [x] `Chart`：`setTheme($name, ?bool $animate = null)` —— 绑定 Area 时经独立 `themeAnimator`（600ms easeOutCubic）对所有主题色（背景/网格/坐标轴/文字/tooltip）做 `Color::lerp` 逐帧补间；headless 即时切换。`themeColorsToRows`/`applyThemeRows` 负责 `[r,g,b]`↔int 转换。
- **Status:** complete

### Phase 10: Tooltip 小箭头 + 系列变色 Color::lerp 动画
- [x] `Chart::hoverPointPx(ChartView)` 解析悬停数据点像素坐标（笛卡尔用 `points`/`barHitboxes`，饼/环用扇区中点）；`drawTooltipArrow()` 用 `fillPolygon`/`strokePolygon` 自动贴到离数据点最近的一条边（左/右/上/下），箭头与气泡同色无缝衔接。
- [x] `Chart` 新增独立 `colorAnimator` + `displayColors` 状态；`draw()` 每帧把 `currentSeriesColors()` 注入 `ChartView::$seriesColors`；`CartesianRenderer`/`PieRenderer` 优先读该字段。`recolor(int ...$hex)` 绑 Area 时逐系列 `Color::lerp`（600ms）补间到新调色板，省略参数还原命名色；headless 即时。`colorsToRows`/`rowsToColors` 辅助。
- [x] `examples/chart-demo.php` 加「重新配色」按钮（随机 5 色）；`docs/zh|en/guide/chart.md` 补「系列重新配色(recolor)」「tooltip 小箭头」两节。
- **Status:** complete

## 关键决策 / 约定
- **值/视图分离**：`Dataset`(数据) → `Scale`(nice-number 刻度) → `ZoomState`(缩放域) → `ChartView`(像素映射)，职责清晰、易测。
- **渲染器可插拔**：`RendererFactory::all()` 是单例注册表；新增图表类型只需实现 `ChartRenderer` 接口并在工厂注册。`Line` 作为 fallback。
- **无头可测**：所有几何/动画逻辑不依赖 GUI。测试里用 `Animator::seekTo()` 直接定位补间帧，确定性高；无 `Area` 时 `setData()` 即时同步显示值。
- **手势映射（libui `Area` 限制）**：`Area` 只转发 draw/mouse/mouseCrossed/dragBroken/key，**无原生滚轮/触摸/拖拽事件**。
  - 双击缩放 → 原生 `AreaMouseEvent.count === 2`
  - 捏合 → `Shift + 横向拖拽`（因子 `exp(-dx/pw*2.5)`，锚点固定）
  - 平移 → 已缩放时普通拖拽
  - 框选放大 → 未缩放时普通拖拽出选框，松开 `zoomTo()`
  - 键盘 `+`/`-`/`=`/`0` 缩放/复位
- **tooltip 文字基线（关键认知）**：libui `drawString` 的 y 是**文本顶边**（top-left），不是基线。前几版反复偏上/偏下，根因是猜 baseline 偏移。最终用 `TextLayout::extents()` 实测 `[$tw,$th]`，框宽=`$tw+16`、框高=`$th+8`，文字 `DrawTextAlign::Center + width=$w` 实现水平居中，y=`框顶+4` 实现上下等 padding。

## 关键 API 速查
- `Chart::__construct(ChartType, ?ChartConfig, array $datasets=[])`
- `setData($datasets, $animate=null)` — 仅当 `$animate && $boundArea!==null` 才动画
- `setType(ChartType)` / `setLabels(array)` / `resetZoom()` / `setTheme('light'|'dark', ?bool $animate=null)`（默认动画；headless 即时）
- `recolor(int ...$hex)` — 系列色补间到新调色板，省略参数还原命名色
- `getZoom()` / `getAnimator()`（数据）/ `getThemeAnimator()`（主题色）/ `getColorAnimator()`（系列色）/ `getHover()` / `getConfig()` / `getDisplayValues()` / `getLabels()`
- `ChartConfig`：流式 setter + 直接赋值字段；`THEMES` 预设含 tooltip 配色；`applyTheme($name)` 安全回退 `light`；`colorAt($i)` 超基础 10 色自动生成 HSL 亮度变体；`seriesPalette($count)` / `paletteVariantStep($step)`；`interpolateTheme($a,$b,$t)` 用 `Color::lerp` 逐字段补间
- `AreaDelegate::redraw()` 必须是 `public`（影响测试自动加载）

## Pending / 可选优化（待用户反馈）
- 柱状图顶部数值标签（已做，toggle 按钮在 demo）
- 悬停 tooltip 小箭头指向数据点（**已做**，Phase 10）
- 主题切换 Color::lerp 补间动画（**已做**，Phase 9）
- 系列变色 Color::lerp 动画 / recolor（**已做**，Phase 10）
- 右键拖拽平移作为 Shift+拖拽 的备选触发（未做）
- 包成独立 Composer 包（当前 PSR-4 是 `Yangweijie\Ui2\`）
- 触屏输入适配层（把真·双指捏合翻译到 `zoomAt/pan`）
- 新设想（上一轮结尾提的可选增强，未做）：① tooltip 箭头「轻微弹入」微动画 ② `setData` 数据点数变化时新系列颜色 fade-in

## Errors Encountered
| 现象 | 根因 | 解决 |
|------|------|------|
| `AreaDelegate::redraw() must be public` | `Chart::redraw()` 写成 private | 改为 public |
| `Interface ChartRenderer not found` | 文件命名 `RendererInterface.php` 映射到类名 `RendererInterface` | `mv` → `ChartRenderer.php` |
| `expect()->toBeApproximately()` 不存在 | 该 Pest 版本无此方法 | 全改 `toEqualWithDelta`;`toBeBetween` 改显式 `toBeGreaterThanOrEqual`+`toBeLessThanOrEqual` |
| 拖拽平移"没效果" | `ZoomState::pan()` 在全域 clamp 回 full，数学上无处可移 | 未缩放→box-zoom，已缩放→pan |
| tooltip 文字偏上→偏下→仍不对（多轮） | 误把 `drawString` 的 y 当基线 | 改用 `TextLayout::extents()` 实测尺寸，顶边对齐 + 居中 |
