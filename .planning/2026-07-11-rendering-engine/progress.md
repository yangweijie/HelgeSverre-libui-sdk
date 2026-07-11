# Progress — 自绘渲染引擎

## Session 1（2026-07-11）
- 用户提案四层自绘引擎架构；本 agent 核实仓库现状后给出 4 处 PHP/libui 修正（enum→类继承、
  text(string)→text(TextLayout)、砍阴影、裁剪用命令树），并建议 Slice 1 竖切 CircleProgressBar 验证链路。
- 用户指令：新建 `2026-07-11-rendering-engine` plan，Slice 1/2/3 → Phase 1/2/3，从 Slice 1 开始实现。
- 已 scaffold 独立 plan 并设为 active。

### Phase 1（Slice 1：RenderCommand 层）— 完成
- [x] planning 三件套建立（task_plan/findings/progress）
- [x] 实现 RenderCommand 类体系 + CommandExecutor
      - `src/Rendering/`：抽象基类 `RenderCommand` + 具体命令 `StrokeArc` / `FillRoundedRect` /
        `StrokeRoundedRect` / `FillCircle` / `StrokeCircle` / `DrawText` / `SaveClip`
        （**每类一文件**，PSR-4 按类名即可加载）
      - `RenderCommandList`（持有命令 + `free()` 释放 TextLayout/Path）、`CommandExecutor`（`match(true)` 分发到 DrawContext）
- [x] `CircleProgressDelegate` 拆为独立文件；`draw()` 改为 `buildCommands → execute → free`
      - 抽出纯函数 `geometry()` / `arcCommands()`（headless 可测，不含 TextLayout）/ `buildCommands()`
      - 圆环用 `strokePath` + `Path::arc` 还原（**非** `strokeArc`/wedge），渲染像素级不变
- [x] `tests/RenderingTest.php` **6 passed**（arc 几何/sweep/居中/颜色/round-cap/空表 free 安全）；`ChartTest` **24 passed** 无回归
- 验证边界：命令编译层 headless 全绿；Executor 真实绘制（libui 需显示器）由 demo 验收

### Errors Encountered（Slice 1）
| 现象 | 根因 | 解决 |
|------|------|------|
| `CircleProgressDelegate` 类找不到 | 原定义在 `CircleProgressBar.php`，PSR-4 按类名找 `CircleProgressDelegate.php` 失败 | 拆为独立文件 `src/Widgets/CircleProgressDelegate.php` |
| `Yangweijie\Ui2\Rendering\StrokeArc` 找不到 | 所有命令类塞进 `RenderCommand.php`，与 PSR-4「文件名=类名」冲突 | 基类留 `RenderCommand.php`，每个具体命令独立文件 |
| 测试断言「thickness 过大→空列表」失败 | 几何里 `radius = thickness/2 + 4` 恒 >0，原 `radius<=0` 早退是防御性死代码 | 改为验证「小视口回退到内容尺寸并重居中」这一真实逻辑 |

## Session 2（2026-07-11，续）
- 用户确认 Slice 1 在真窗口运行验收通过，指令「Phase 2 继续」。
- 本 agent 实现 DesignTokens（不可变 token 树 + `applyTheme()` 返回新实例 + 递归引用解引用），
  并把 CircleProgressBar / ToggleSwitch 的硬编码色改为读 token；StatusIndicator 核实无需改。

### Phase 2（Slice 2：Design Tokens）— 完成
- [x] `src/Rendering/DesignTokens.php`：默认含 color/radius/typography；`resolve/color/number/has` +
  `applyTheme`（深合并、不可变）
- [x] `CircleProgressDelegate`：`progressColor()`（显式 `setColor` 优先 > `color.primary`）+ track/onSurface 走 token
- [x] `ToggleSwitch`：`ToggleDelegate.draw` 5 处硬编码色改读 token；控件 `setTheme()`；`EmitsEvents` 不变
- [x] `tests/DesignTokensTest.php` **6 passed**；`RenderingTest` 增 2 条主题断言（覆盖换色 + setColor 优先）共 **8 passed**；`ChartTest` **24 passed** 无回归
- 主题生效机制：draw 时现解 token → `setTheme()` 换引用即自动换色，渲染器零改动

### Errors Encountered（Slice 2）
| 现象 | 根因 | 解决 |
|------|------|------|
| ToggleSwitch 构造函数签名被误删，出现「字段后裸 `{`」 | 两次 Edit 的 old_string 重叠，第二次把 `public function __construct(...)` 行误删 | 重新 Read 定位，补回构造函数签名 + 字段 |

## Session 3（2026-07-11，续）
- 用户指令「继续」→ 推进 Phase 3（Slice 3：Widget Renderer 注册表）。

### Phase 3（Slice 3：Widget Renderer 注册表）— 完成
- [x] `src/Rendering/WidgetRenderer/`：
      - `WidgetSpec`（抽象基类，`type()` 判别符）+ `ButtonSpec` / `CardSpec`（不可变值对象）
      - `WidgetRenderer` 接口：`shapeCommands(WidgetSpec,DesignTokens,w,h):RenderCommand[]`（纯几何，headless 可测）
        + `render(...):RenderCommandList`（全量，含 DrawText，需 GUI 验收）
      - `ButtonRenderer`：filled/soft/outline/disabled/pressed 变体；bg/border/text 全读 `DesignTokens`
        （primary/track/onSurface/surface）；pressed 时 primary 派生色 ×0.85 变暗；文本经 DrawText 居中
      - `CardRenderer`：surface 填充 + 可选 track 边框 + elevation 伪阴影（偏移低透明圆角矩形）；无文本故 `render()` 全 headless 安全
      - `RendererRegistry`：`register/has/get/types` + `default()` 预注册 button/card；`get()` 返回 null 即原生 fallback 信号
- [x] 消费方 `src/Widgets/RendererButton.php`：继承 `Composite`，`Area::scrolling` + `RendererButtonDelegate` 从注册表取
      `ButtonRenderer` 自绘；`preferNative=true` 或注册表无 `button` → 构造原生 `Libui\Button`（同一公共 API）；
      鼠标 `down===1 在范围内` 置 pressed 并重绘，`up===1 且 pressed 且在范围内` → `fireClick()`（emit 'click'）；
      `setLabel/setEnabled/setTheme` 两种模式都支持（原生走 `setText`/忽略）
- [x] `tests/WidgetRendererTest.php` **12 passed**（注册表查找、各变体几何断言、pressed 变暗、空标签 render 无 DrawText 且 `free()` 安全、卡片边框/阴影/纯 surface）
- [x] `examples/renderer-button-demo.php`：自绘按钮行 + 原生 fallback 行 + `CardRenderer` 直绘 Area + 主题切换，供 GUI 验收
- [x] 全量 pest：`WidgetRenderer 12` + `Chart 24` + `Rendering 8` + `DesignTokens 6` 全绿；
      `CircleProgressBarTest` 的 `SIZE` 常量失败为 **Phase 1 之前已存在**的陈旧测试（`Undefined constant CircleProgressBar::SIZE`），
      与 Phase 3 无关（本次仅新增文件，未改任何既有文件），按前序决策保留不修
- bash cwd 注意：Bash 工具的工作目录是 `/Volumes/data/git/php`，本仓库在子目录
      `HelgeSverre-libui-sdk`；相对路径命令需 `cd /Volumes/data/git/php/HelgeSverre-libui-sdk &&`，否则会落到错误的 `src/`

*规划文件遵循 planning-with-files 技能：task_plan.md=阶段追踪，findings.md=技术发现，progress.md=会话日志。本 plan 独立于图表 plan（`.planning/2026-07-11-chart-component`）。*
