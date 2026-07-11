# 自建布局引擎 + 全自绘控件（参考 native SDK / GPUI 风格）

## 背景动机
libui 原生容器（Box/Group）给 Area 无 intrinsic 尺寸、无 flex/grid 模型，
导致自绘控件（RendererButton）连续 8 轮塌陷/溢出/点不到。结论：不再依赖
libui 容器，改用「单画布 Area + 自建布局引擎 + 全自绘控件 + 事件路由」，
对齐用户贴的 native SDK（GPUI 风格）架构。

## 已完成
- Phase 4 ✅ Flexbox 布局引擎（src/Layout/LayoutStyle.php / LayoutNode.php /
  FlexLayout.php + tests/FlexLayoutTest.php 16 passed）。
  - direction(row/column)、gap、padding、justify(start/center/end/spaceBetween/
    spaceAround/spaceEvenly)、align(start/center/end/stretch)、grow、shrink、
    basis、fixed width/height、嵌套递归。
  - 纯 PHP、headless 可测；固定尺寸优先于 stretch。

## 待办（按优先级）
- Phase 5：Grid 布局引擎（GridLayout.php）—— columns/rows/template、
  gap、跨列跨行、虚拟化可选。复用 LayoutNode，新增 GridStyle。
- Phase 6：Surface 画布控件（src/Widgets/Surface.php）—— 持有单个非滚动
  Area + SurfaceDelegate：draw() 跑 FlexLayout/Grid → 遍历 LayoutNode 用
  WidgetRenderer 注册表绘制每个叶子 → CommandExecutor；mouse() 按计算出的
  rect 命中测试 → 路由到叶子控件回调。这一步直接替代 libui Box/Group，
  彻底解决 RendererButton 尺寸痛点。
- Phase 7：扩充 WidgetRenderer（注册表已支持 button/card）—— 按 HTML→widget
  映射补：input/text_field、checkbox、radio、slider、progress、select/dropdown、
  list、table/data_grid、dialog/drawer/sheet/popover、tabs/breadcrumb/pagination。
  每个一个 Spec + Renderer，shapeCommands(headless) + render(GUI)。
- Phase 8：事件系统（src/Events/）—— 指针 hover/down/move/up/wheel(含点击计数) +
  键盘 key_down/up/text_input + 焦点捕获。映射到 Surface 的 LayoutNode rect。
- Phase 9：语义/无障碍（src/Semantics/）—— WidgetRole 映射（dialog/menu/grid
  等），焦点管理、键盘导航暴露给平台无障碍桥（libui 侧能力有限，尽力而为）。
- Phase 10：Design Token 扩展 —— 补齐 native SDK 的 ControlVisualTokens
  对应字段（hover/disabled wash、focus ring、hairline 1px 对齐 snapHairline）。

## 关键约束（落地时遵守）
- 本库 PSR-4 `Yangweijie\Ui2\`→`src/`，每类一文件。
- 布局引擎必须纯函数、headless 可测（不依赖 libui / GUI）。
- 自绘控件统一走 WidgetRenderer 注册表；注册表 get()=null → 原生 fallback。
- Surface 用非滚动 Area + 父容器 stretchy 拿到画布尺寸（ToggleSwitch 同构）。
- TextLayout 生命周期：retained 命令自持，draw 后 free()。
