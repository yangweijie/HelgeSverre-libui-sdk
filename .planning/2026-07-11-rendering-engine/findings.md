# Findings — 自绘渲染引擎

## DrawContext 真实 API（核实自 vendor/helgesverre/libui/src/Draw/DrawContext.php）
- 填充/描边原语：`fillRect` `fillCircle` `fillRoundedRect` `fillEllipse` `fillPolygon`
  `strokeRect` `strokeCircle` `strokeRoundedRect` `strokeEllipse` `strokePolygon` `strokeLine`
  `line` `dot` `fillArc` `strokeArc`。
- 均接受 `Brush|Color`（Color 自动转 Brush::color）。`stroke*` 可选 `?StrokeParams`，缺省 `solid(1.0)`。
- 路径：`fillPath(Brush, callable $build, $mode)` / `strokePath(Brush, StrokeParams, callable $build)`。
  `$build(Path $p)` 里调 `addRectangle/circle/ellipse/roundedRect/arc/wedge/polygon/line`，库内 `end()`+free。
- `save()`/`restore()`/`withSave(callable)`/`clip(Path)`/`transform(Matrix)` 可用。
- `text(TextLayout $layout, float $x, float $y)` —— **只吃 TextLayout**，不是 string。
- `drawString(string, FontDescriptor, Color|array, x, y, ?width, align)` 是便捷封装：
  内部 `new AttributedString` + `Attribute::fromColor` + `new TextLayout` + `text()` + `layout->free()` + `string->free()`。
  ⚠️ 此便捷封装**未设 `Attribute::size`**，仅字号来自 FontDescriptor；当前 CircleProgressBar 用
  自己手写的 `Attribute::size($fontSize)`，二者在字号渲染上等价（字号都在 FontDescriptor 里）。

## ⚠ 关键坑：strokeArc 用 wedge，不是 arc
- `DrawContext::strokeArc(...)` 内部是 `Path::wedge(cx,cy,r,start,sweep)`。
  `wedge` = 弧 + 两条到圆心的半径线，描边时会画出「披萨切片」的两条径向边。
- 现有 `CircleProgressBar` 用的 `Path::arc(...)` 只画弧线（整圆时就是圆环），**无径向边**。
- 因此 Phase 1 的 `RenderCommand\StrokeArc` **必须**用 `strokePath(Brush, StrokeParams, fn($p)=>$p->arc(...))`
  还原，绝不能调 `strokeArc`。否则 pixel 不一致。

## 文本资源生命周期
- libui 的 `TextLayout` / `AttributedString` 是 FFI 资源，`__destruct` 不可靠（GC 时机问题，
  见 DrawContext::drawString 注释）。现有 CircleProgressBar 在 draw() 末尾 `$layout->free()`。
- 命令被 retained（跨帧存储）时，必须由 `RenderCommandList::free()` 负责释放持有的 `TextLayout`，
  否则下一帧画到已释放布局。
- Phase 1 的 delegate 每帧重建命令列表，draw 末尾调 `$list->free()`，与原 `$layout->free()` 行为一致。

## 现有自绘控件（Phase 2 接 token 结果）
- `CircleProgressBar`：原 `DEFAULT_COLOR/TRACK_COLOR/TEXT_COLOR` 三处硬编码已改为读 `DesignTokens`
  （`color.primary` / `color.track` / `color.onSurface`）。`progressColor()` 优先级：显式 `setColor` > token。
- `ToggleSwitch`：`ToggleDelegate.draw` 原 5 处 `Color::rgba(...)` 硬编码改为读
  `color.toggleOn/toggleOff/toggleBorder/knob/knobBorder`。`EmitsEvents` 的 mouse→toggle→emit 链路不变。
- `StatusIndicator`：经核实**无硬编码调色板色**——唯一颜色是用户语义色（`$this->color`）及其派生的 0.25 alpha
  光晕。强行加 token 无意义，Phase 2 如实跳过。
- 三者都继承 `Composite`，可被布局容器以「原生控件」方式持有（Composite 模式已验证）。

## DesignTokens（Phase 2 新增，src/Rendering/DesignTokens.php）
- 不可变 token 树，默认 `DEFAULT` 含 color（primary/track/onSurface/surface/knob/toggleOn/toggleOff/
  toggleBorder/knobBorder）/radius（sm/md/lg/full）/typography.body。颜色用仓库原生 `[r,g,b,a]` 0..1。
- `resolve($path)` 按 `.` 拆段导航；**字符串值当引用路径递归解引用**（深度 >16 抛 cycle 异常），
  所以 `'brand' => 'color.primary'` 这类引用可用。
- `color($path)` → `Color::rgba(...resolve())`；`number($path)` 取标量；`has($path)` 容错。
- `applyTheme($overrides)`：深合并返回**新实例**，原对象不变（与 Chart 主题模型一致，side-effect free）。
- 主题在「draw 时现解 token」生效：控件持 `DesignTokens`，`setTheme()` 换引用并重绘即自动换色——无需
  改渲染器。headless 验证走 delegate 级（CircleProgressDelegate 读 `tokens`）与 DesignTokens 单测。

## PHP 语法约束（相对 Native SDK 提案的修正）
- PHP `enum` 每个 case 只能挂单个标量值，不能像 Rust 那样 `case FillRect(float...)`。
  → 改用 `abstract class RenderCommand` + `final class Xxx extends RenderCommand`。
- `CommandExecutor` 用 `match (true) { $c instanceof Xxx => ... }` 分发（与 Chart 内现有 match 风格统一）。
- **PSR-4 强制每类一文件**：本库 autoload 是 `Yangweijie\Ui2\` → `src/`（前缀映射，运行时按类名拼路径）。
  把多个类塞进一个文件（如把 7 个命令类都放 `RenderCommand.php`）会 `class not found`。
  → 抽象基类可单独成文件，每个具体命令必须独立文件（`StrokeArc.php` 等）。同理 `@internal` 的
  `CircleProgressDelegate` 原本内嵌在 `CircleProgressBar.php`，测试按类名加载失败，已拆为独立文件
  `src/Widgets/CircleProgressDelegate.php`。

## 测试
- PHP 单测无法 headless 启动真实 DrawContext（libui 需显示器），故 Executor 的真实绘制路径
  只能在 GUI demo 验收。
- 可 headless 测的是 **命令编译层**：把 delegate 的 `draw()` 拆出纯函数 `buildCommands()`，
  直接断言返回的 `RenderCommandList` 的命令数/参数。`tests/RenderingTest.php` 即此思路。
- 运行：`php85 vendor/bin/pest tests/RenderingTest.php --no-coverage`

## Phase 3：WidgetRenderer 注册表（L3 声明式雏形）
- 目录 `src/Rendering/WidgetRenderer/`：PSR-4 `Yangweijie\Ui2\Rendering\WidgetRenderer\*` → 每类一文件。
- `WidgetSpec`（抽象基类，`type():string` 判别符）+ 具体 `ButtonSpec`/`CardSpec`（不可变 `readonly` 值对象）。
- `WidgetRenderer` 接口双方法：
  - `shapeCommands(WidgetSpec,DesignTokens,w,h):RenderCommand[]` —— **纯几何，无 TextLayout**，headless 可测；
  - `render(...):RenderCommandList` —— 在 shape 之上加 DrawText（构造 TextLayout，需 GUI）。
  与 Phase 1 的 `CircleProgressDelegate::arcCommands()`/`buildCommands()` 同构：把「可测几何」与「需 GUI 的文本」分离。
- `RendererRegistry`：`register(WidgetRenderer)` 按 `$r::type()` 存；`get($type):?WidgetRenderer`（null = 原生 fallback 信号）；
  `default()` 预注册 `ButtonRenderer`+`CardRenderer`。控件的 fallback 决策：`$useNative = $preferNative || !$registry->has('button')`。
- `ButtonRenderer` 变体逻辑（全读 token）：
  - filled：bg=primary（pressed 时 ×0.85），text=surface，无边框
  - soft：bg=track，border=primary，text=primary
  - outline：无 bg，border=primary（pressed ×0.85），text=primary
  - disabled：bg=track，无边框，text=onSurface@0.5 alpha
- `CardRenderer`：elevation>0 → 先画偏移低透明（onSurface@0.12*elevation）圆角矩形伪阴影；再 surface 填充；bordered → track 描边。
  无文本，故 `render()` 也 headless 安全。
- 消费方 `RendererButton`（`src/Widgets/RendererButton.php`）：`extends Composite`，持 `Area::scrolling($delegate,W,H)`；
  delegate 的 `draw()` 用 `registry->get('button')->render(spec,tokens,w,h)` → `CommandExecutor::execute` → `list->free()`；
  鼠标 `down===1 且在范围内` 置 pressed 并重绘，`up===1 且 pressed 且在内` → `fireClick()`（emit 'click'）。
  `preferNative` 或注册表缺失时构造原生 `\Libui\Button`（同一 `on('click')`/`setLabel`/`setEnabled`/`setTheme` API）。
- `RendererButton` 与 delegate 因构造 `Area`/`Libui\Button` 需 GUI，**不可 headless 实例化**；其逻辑由
  `WidgetRendererTest`（注册表 + Renderer 几何，12 passed）与 `examples/renderer-button-demo.php`（GUI）覆盖。
- 全量 pest：`CircleProgressBarTest` 的 `SIZE` 常量失败为 Phase 1 前已存在的陈旧测试（`Undefined constant CircleProgressBar::SIZE`），
  与 Phase 3 无关（本次仅新增文件）。

## 工具坑（Bash 工作目录）
- Bash 工具 cwd 是 `/Volumes/data/git/php`，本仓库在 `HelgeSverre-libui-sdk` 子目录；
  相对路径会落到错误的 `src/`，命令需以 `cd /Volumes/data/git/php/HelgeSverre-libui-sdk &&` 开头。
- 运行测试用 `php85`（PHP 8.5，已加载 libui 扩展）；`php`（8.4.19）可能缺扩展。
