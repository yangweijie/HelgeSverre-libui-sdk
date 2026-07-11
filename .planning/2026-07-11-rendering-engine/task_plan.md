# 自绘渲染引擎（Rendering Engine）

## 目标
为 libui/PHP SDK 建立一套分层自绘抽象，把「逐个控件手写 DrawContext 调用」升级为
「声明式描述 → 编译成 RenderCommand → Executor 翻译执行」，从而支撑跨平台一致的
可主题化控件族，而不用重写全部原生控件。

参照用户提案的四层架构（L1 底层不变，新建 L2/L3/L4），按 3 个 Slice 渐进落地。

## 关键架构决策（动手前已对齐）
- **L1 不动**：复用现有 `DrawContext`（`fillRoundedRect`/`strokeRoundedRect`/`fillCircle`/
  `strokeCircle`/`fillPolygon`/`withSave`/`clip`/`text`）。
- **PHP 枚举语法限制**：`enum RenderCommand` 写不出带多参数的 case（那是 Rust 语法）。
  改用 **抽象基类 + 具体 final 子类** 体系。
- **文本命令携带 `TextLayout`**：`DrawContext::text()` 只吃 `TextLayout`（不是 string+font+color）。
  命令层需正确持有并在命令列表替换/控件销毁时 `free()`，否则会画到已释放布局。
- **libui 无 shadow/blur 原语**：`RenderCommand::Shadow` 砍掉（或后续用偏移半透明矩形伪造）。
- **裁剪是命令树节点**：`SaveClip` 带 `children`，Executor 进入时 `withSave(fn()=>{clip;递归children})`，
  push/pop 天然配对。不用平铺的 PopClip。
- **圆环必须用 `strokePath` + `Path::arc`，不能用 `strokeArc`**：`DrawContext::strokeArc` 内部走
  `Path::wedge`（会描出到圆心的两条半径），而现有 CircleProgressBar 用的是 `Path::arc`。
  用错会破坏像素级还原。

## Phase 1: RenderCommand 层（Slice 1）
- [x] 新建 `src/Rendering/RenderCommand.php`：抽象基类 + `StrokeArc` / `FillRoundedRect` /
      `StrokeRoundedRect` / `FillCircle` / `StrokeCircle` / `DrawText` / `SaveClip` 具体命令
- [x] 新建 `src/Rendering/RenderCommandList.php`：持有 `commands[]` + `free()`（释放 TextLayout 等）
- [x] 新建 `src/Rendering/CommandExecutor.php`：`match(true){...}` 分发到 DrawContext
- [x] 重构 `CircleProgressDelegate`：抽出纯函数 `buildCommands(): RenderCommandList`（不依赖 ctx，
      可 headless 测）；`draw()` 改为「build → executor 执行 → list->free()」
- [x] 渲染结果像素级不变（track/progress 用 `strokePath`+`arc`；text 用原 `TextLayout` 构建）
- [x] 新增 `tests/RenderingTest.php`：断言 `buildCommands()` 在 progress=65/0 的命令数、sweep、坐标
- **Status:** complete

## Phase 2: Design Tokens（Slice 2）
- [x] 新建 `src/Rendering/DesignTokens.php`：不可变 token 树（color/radius/typography），
      用仓库现成的 `[r,g,b,a]` 0..1 格式；`resolve(string $path)` 递归解引用（字符串值当引用路径）；
      `color()/number()/has()` 便捷读取；`applyTheme($overrides)` 深合并并返回**新实例**（不改原对象）
- [x] `CircleProgressDelegate` 去硬编码色：track→`color.track`、进度弧→`progressColor()`（显式
      `setColor` 覆盖优先，否则读 `color.primary`）、文字→`color.onSurface`；控件持 `tokens` + `setTheme()`
- [x] `ToggleSwitch`：`ToggleDelegate.draw` 改为从 token 读 `toggleOn/toggleOff/toggleBorder/knob/knobBorder`；
      控件持 `tokens` + `setTheme()`；`EmitsEvents` 链路不变（构造函数加可选 `?DesignTokens` 参数，向后兼容）
- [x] `StatusIndicator` 经核实**无可主题化硬编码色**（仅用户传入的语义色 + 由它派生 0.25 alpha 光晕），
      按规划如实跳过，不强行加 token
- [x] 验证：主题在「draw 时按 token 现解」→ 切 token 即自动换色；headless 用 delegate 级 + DesignTokens 单测覆盖
- **Status:** complete

## Phase 3: Widget Renderer 注册表（Slice 3）
- [x] 新建 `src/Rendering/WidgetRenderer/` 层：
      - `WidgetSpec`（抽象描述基类）+ `ButtonSpec` / `CardSpec`（不可变描述值对象）
      - `WidgetRenderer` 接口（`shapeCommands()` 纯几何 headless 可测 / `render()` 全量含文本）
      - `ButtonRenderer`（filled/soft/outline/disabled/pressed 变体，读 token 产 Fill/StrokeRoundedRect）
      - `CardRenderer`（surface 填充 + 可选边框 + 伪阴影 elevation，无文本故全 headless 安全）
      - `RendererRegistry`（register/has/get/types + `default()` 预注册 button/card；
        `get()` 返回 null 即「原生 fallback」信号）
- [x] 新建消费方 `src/Widgets/RendererButton.php`：继承 `Composite`，持 `Area`+`RendererButtonDelegate`
      从注册表取 `ButtonRenderer` 自绘；`preferNative=true` 或注册表无 `button` → 回退原生 `Libui\Button`，
      公共 API（label/enabled/theme/click）两种模式一致；鼠标 down+up 在范围内触发 click
- [x] 新增 `tests/WidgetRendererTest.php` **12 passed**（注册表查找、各变体几何、pressed 变暗、空标签 render 安全、卡片边框/阴影）
- [x] 新增 `examples/renderer-button-demo.php`：自绘按钮行 + 原生 fallback 行 + CardRenderer 直绘 Area + 主题切换，供 GUI 验收
- [x] 全量 pest：WidgetRenderer 12 + Chart 24 + Rendering 8 + DesignTokens 6 全绿；
      `CircleProgressBarTest` 的 `SIZE` 常量失败为 **Phase 1 前已存在**的陈旧测试（无关，未触碰）
- **Status:** complete

## 关键 API 速查
- `DrawContext`：真实可用 `fillRect/fillCircle/fillRoundedRect/strokeRect/strokeCircle/
  strokeRoundedRect/strokeLine/fillPolygon/strokePolygon/strokeArc(⚠wedge)/strokePath/
  fillPath/text(TextLayout)/drawString/withSave/clip/transform`
- `Path`：`addRectangle/circle/ellipse/roundedRect/arc(用于圆环)/wedge/polygon/line`
- `RenderCommandList::build/or` 由 delegate 产出；`CommandExecutor::execute($ctx, $list)`
- `DesignTokens`：`resolve($path)`/`color($path)`/`number($path)`/`has($path)`；
  `applyTheme($overrides)` 返回**新实例**（深合并，不可变）；字符串 token 值当引用路径递归解引用

## Pending / 可选优化（待用户反馈）
- 阴影：用偏移半透明圆角矩形伪造 elevation（无真模糊）
- Slice 3 是否要 DSL（`ui(column(...))`）还是先只做 Renderer 类
- 命令录制/回放（record/replay）作为 RenderCommand 的进阶能力
