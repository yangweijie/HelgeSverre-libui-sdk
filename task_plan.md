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

## Tetris 示例 — 任务计划

> 目标：用 ui2 自绘体系实现一个完整可玩的俄罗斯方块示例

| Phase | 内容 | 状态 | 备注 |
|-------|------|------|------|
| T1 | 基础游戏逻辑：棋盘、7 种方块、旋转、消行、计分、等级 | ✅ complete | `examples/tetris.php` |
| T2 | Area + AreaDelegate：游戏区域自绘（网格、方块、幽灵、硬降） | ✅ complete | 键盘 + draw 回调 |
| T3 | 侧边栏：标签 + 分数 + NEXT 预览 | ✅ complete | 单 Area 全自绘，drawString + fillRect |
| T4 | LayoutNode/Surface 侧边栏方案探索 | ✅ 完成但放弃 | Surface 内部 Area 在 Box 中拿不到固定宽度，被挤出窗口。结论：游戏类布局不适合 Surface |
| T5 | LabelSpec text 可变支持 | ✅ complete | `src/Rendering/WidgetRenderer/LabelSpec.php` — `text` 从 `readonly` 改为 mutable |
| T6 | 全部画进单个 Area：游戏区域左 + 侧边栏右 | ✅ complete | 消除 Area 竞争，draw 回调中直接绘制所有内容 |
| T7 | 游戏区域垂直居中 + GAME OVER/PAUSED 覆盖层居中 | ✅ complete | boardY 偏移 + `extents()` 手动测量居中（`DrawTextAlign::Center` 在 macOS 不可靠） |
| T8 | 动态标签实时更新 | ✅ complete | draw 回调直接拼接 state 变量，每次重绘自动更新 |

### 关键发现
- **Surface 不能放在 Box 中与另一个 Area 共存**：Surface 内部创建非滚动 Area，libui Box 无法给它固定宽度
- **DrawTextAlign::Center 在 macOS 不可靠**：CoreText 渲染比逻辑布局框更宽导致偏移，需用 `extents()` 手动测量
- **游戏类布局最佳方案**：单个 Area + AreaDelegate 画全部（游戏区域 + 侧边栏），不依赖 Surface
- **LabelSpec text 应为 mutable**：动态内容（分数/等级/行数）需要运行时修改文本
- **`Color::__construct()` 是 private**：必须用 `Color::rgb()` / `Color::rgba()` 工厂方法
- **未来架构建议**：为 Surface 增加 `CanvasSpec`，让 Surface 的 LayoutNode 树能嵌入自定义绘制回调（游戏/图表等）

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
| G | 状态管理（Elm Architecture）：Model/Msg/Effect/UpdateResult/AppRuntime 抽象 | ✅ complete | `src/State/` × 5 个文件 |
| H | UI 声明（DSL）：.native XML → LayoutNode 编译器（NativeLoader + 19 个 Spec） | ✅ complete | `src/Compiler/` × 2 文件 + `examples/counter.native` |
| I | TextArea IME 中文输入回显修复（五重 bug：segfault + withState 丢失 control + stale value + callback GC + observer block-based） | ✅ complete | `Surface.php` + `TextAreaControl.php` + `ime_bridge.m` — segfault / spec 丢失 / callback GC 全修复，用户确认中文回显正常 |
| J | 表单字段 IME 覆盖层（searchField/textField）"幽灵重叠"修复（四重 bug：销毁未执行 + 字号不一致 + 首焦不可见 + 滚动跟随） | ✅ complete | `Surface.php` + `ime_bridge.m` + `TextFieldRenderer/SearchFieldRenderer` — 递归整窗清扫、字号参数化、逐帧重定位、typingAttributes 统一。用户截图确认全部正常 |
| K | ime_bridge 跨平台（Windows/Linux）+ 接入构建系统 | ✅ complete | `bridge/ime_bridge_win.c`(EDIT) + `bridge/ime_bridge_linux.c`(GTK3) 三平台同符号；`Surface.php` 加 `imeBridgePath()` 按 `PHP_OS_FAMILY` 选库；`composer.json` 新增 `build:ime` 并纳入 `build` 聚合。macOS `composer build:ime` 编译+符号导出验证通过 |
| L | 「全面转向自绘」简化可行性审计 | ✅ complete | 依赖面勘察：原生 `Fields/*` + `*Control` 被 4 测试 + 多个示例 + IME 覆盖层（`Surface.php:1094` / `TextAreaSpec.$control`）引用；自绘 Spec 缺 DatePicker/FilePicker/Password/Number。结论：当前为混合架构，不可直接删除 |
| M | 补自绘 Spec 缺口（DatePicker/FilePicker/Password/Number） | ✅ complete | `Number`/`Password`/`Date`/`File` 四组 Spec+Renderer 全部加入并注册（`date_picker`/`file_picker` 画成只读字段+右侧 chevron，点击由 Surface `onClick` 调 `DatePickerDialog`/`FilePickerDialog`）。新增 `src/Pickers/FilePickerDialog.php` 补 OS 文件框（libui `Dialogs::openFile()` 封装），与 `src/Pickers/` 家族对称。5 文件 `php85 -l` 全过，registry headless 注册验证通过 |
| N | IME 性能优化 + （可选）解耦原生控件 | ✅ complete | 性能优化（用户实测"不卡了"确认生效）：① IME 调试日志门控到 `UI2_DEBUG_IME=1`（去掉每键 ~5 次 `fflush(STDERR)` + 每帧 `withState` 写），② bridge cdef 每实例只解析一次（缓存 `$imeBridgeCdef`）。另修 `imeDbg()` 可见性 bug（`private`→`public`，否则 `SurfaceDelegate` 调用触发致命错误导致多行文本框不显示）。**深层"去原生控件"重写暂不做**：去掉原生 NSTextView 即丧失中文 IME 能力 |
| O | 迁移示例/测试到自绘 Spec | ✅ complete | 示例（`test-fields.php`）早已自绘；`tests/FieldsTest.php` 现已收尾：由测原生 `Fields\*`（断言 `root() instanceof Control`/`value()`）改写为测**自绘 Spec 值对象** + 断言每个字段 `type()` 在 `RendererRegistry::default()` 已注册（"原生字段有自绘 renderer 接管"）。20 项全过，headless 无需 FFI。原生 `src/Fields/*` 仍保留（Phase P 被 IME 阻塞），但测试已脱离原生 API，Phase P 落地即无覆盖缺口。`tests/FieldsTest.php` 不再引用原生 `Fields\*` |
| P | 删除原生封装（Fields/* + *Control + Generated 控件类） | 🟡 partial | Fields/\* 已全部删除（14 个文件），examples 已迁移为原生 Separator/Entry 或自绘 Spec。tests/FieldsTest.php 20 项全过。剩余：`*Control`（TextAreaControl、SearchFieldControl）仍保留，因 IME 覆盖层依赖其 TextInputControl 接口。 |

## ChartV2 示例 — 任务计划

> 目标：基于 `src/ChartV2/` 组件创建自绘图表示例，替代旧的 `chart-demo.php`

| Phase | 内容 | 状态 | 备注 |
|-------|------|------|------|
| C1 | 创建 `examples/chart-v2-demo.php`（柱状/折线/面积/饼图/散点切换 + 随机数据 + 主题 + 配色 + 数值标签） | ✅ complete | 179 行 |
| C2 | 删除旧 `examples/chart-demo.php`（基于 `src/Chart/` 的旧示例） | ✅ complete | |
| C3 | 修复 ChartWidget：Area nullable 构造 + bindArea 覆写 + getData 方法 | ✅ complete | `src/ChartV2/ChartWidget.php` |
| C4 | 修复 ChartRenderer 6 个 bug | ✅ complete | 见下方错误记录 |

### ChartRenderer 修复记录

| Bug | 根因 | 修复 |
|-----|------|------|
| `ChartData` 类型不匹配 | `ChartRenderer` 在 `WidgetRenderer` 命名空间，没导入 `ChartV2\ChartData` | 添加 `use ChartV2\ChartData` |
| `color.fontFamily` token 不存在 | `fontFromTokens()` 错误地用 `$tokens->color()` 读字体族 | 改为硬编码字体族字符串 |
| `$font->size` 属性不存在 | `FontDescriptor` 的 size 是方法不是属性 | 改为 `$font->size()` |
| `FillRoundedRect` 参数类型 int→Color | `getPaletteColor` 返回 int，FillRoundedRect 需要 Color | 用 `Color::rgb()` 包装 |
| `DrawTextAlign` 参数类型不匹配 | `drawTextCommand` 声明 `int` 但传入 `DrawTextAlign` 枚举 | 改为 `int\|DrawTextAlign` 联合类型 |
| `StrokeCircle` 类未导入 | 同命名空间问题，解析到不存在的 `WidgetRenderer\StrokeCircle` | 添加 `use Rendering\StrokeCircle` |
| `RenderCommandList` 无 `width`/`height` | 缓存比较访问不存在属性 → 抛异常 → 后续绘制全部失败 | 改用独立 `$cachedW/$cachedH` 变量 |
| `showValueLabels` 未实现 | `ChartData` 有字段但 `ChartRenderer` 从没读取 | 在 bar/line/scatter 渲染器中添加数值标签 |
| 重新配色无效 | `makeBarData()` 给 series 设了固定颜色，`$series->color ?? palette` 优先用 series 颜色 | 按钮改为直接修改 `series->color` |

### 关键发现
- **`static` 闭包 + 对象引用**：`static function () use ($chart)` 捕获对象引用，方法调用正常工作
- **命名空间同名冲突**：子命名空间（如 `WidgetRenderer`）中的类如果不显式导入父命名空间的同名类，PHP 解析到子命名空间 → 类似 `StrokeCircle` 找不到
- **`FontDescriptor` 属性 vs 方法**：`size()` 是方法不是属性，PHP 8.x 严格模式下会报 `Undefined property`
- **渲染器缓存**：`RenderCommandList` 是纯数据对象，不应承载缓存元数据（width/height），应在持有者（ChartWidget）中跟踪

## CanvasSpec — Surface 自定义绘制嵌入

> 目标：为 Surface 的 LayoutNode 树增加 `CanvasSpec`，支持嵌入任意 DrawContext 绘制回调

| Phase | 内容 | 状态 | 备注 |
|-------|------|------|------|
| V1 | 新增 `DrawCallback` RenderCommand | ✅ complete | 持有 `\Closure(DrawContext, float, float): void` |
| V2 | 新增 `CanvasSpec` WidgetSpec | ✅ complete | 嵌入回调到 LayoutNode 叶子节点 |
| V3 | 新增 `CanvasRenderer` | ✅ complete | 生成 DrawCallback 命令 + 可选背景色 |
| V4 | `CommandExecutor` 支持 DrawCallback 分发 | ✅ complete | `($cmd->callback)($ctx, $cmd->width, $cmd->height)` |
| V5 | `RendererRegistry` 注册 CanvasRenderer | ✅ complete | |
| V6 | 示例 `examples/canvas-demo.php` | ✅ complete | 迷你折线图 + 柱状图 + 动画进度条 + LabelSpec 混合布局 |

### CanvasSpec 用法
```php
$canvas = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));
        // 任意 DrawContext 绑制...
    },
    background: 0x1E293B, // 可选背景色
);

$layout = LayoutNode::column()
    ->child(LayoutNode::leaf('header', new LabelSpec('Title'), height: 30.0))
    ->child(LayoutNode::leaf('chart', $canvas, height: 200.0));

$surface = new Surface($layout);
```

### 关键设计
- **DrawCallback 是 RenderCommand**：Surface 渲染管线无需修改，CommandExecutor 自动分发
- **`\Closure` 而非 `callable`**：PHP 8.5 不允许 `callable` 作为 readonly 属性类型
- **回调接收 `(DrawContext, width, height)`**：坐标系已由 Surface 的 `withSave()` + `transform()` 平移到节点位置
- **与 LabelSpec 等混合布局**：CanvasSpec 作为 LayoutNode 叶子，与任何其他 Spec 共存
