# Layout Engine — Progress

## Session 1（2026-07-11）
- 用户提出参考 native SDK（GPUI 风格）将所有原生组件自绘 + 自建 Flexbox/Grid。
  动机：刚经历 RendererButton 8 轮 libui 容器尺寸踩坑，确认 libui Box/Group 不可靠。

### Phase 4：Flexbox 引擎 ✅
- LayoutStyle / LayoutNode / FlexLayout + 16 headless 测试。

### Phase 5：Grid 引擎 ✅
- src/Layout/GridTrack.php（fr/px/auto 轨道）、GridStyle.php（columns/rows/gap/align）、
  GridLayout.php（模板列宽、行高、跨列跨行、递归子节点）。
- FlexLayout::layoutChildren 改为 public 供 GridLayout 递归。
- tests/GridLayoutTest.php（7 passed）。

### Phase 6：Surface 画布控件 ✅
- src/Widgets/Surface.php：单非滚动 Area + SurfaceDelegate。
  draw():跑 FlexLayout→translate 到节点 rect→按 spec.type() 取 RendererRegistry 绘制。
  mouse():findAt 命中→路由 click/double-click→handler；pressed 视觉。
  彻底脱离 libui Box/Group，根治自绘控件尺寸痛点。
- examples/surface-demo.php（行布局自绘按钮 + 卡片 + 原生 Label 状态栏 + 主题切换）。

### Phase 7：扩充 WidgetRenderer ✅
- 新增 6 控件：Checkbox/Radio/Slider/Progress/TextField/Select（Spec + Renderer 各一文件）。
- 新增绘图命令 StrokeLine / FillPolygon（勾选线、下拉箭头），CommandExecutor 分发。
- 全部注册到 RendererRegistry::default()。
- tests/WidgetRendererExtendedTest.php（9 passed；渲染器共 21 passed）。

### Phase 8：事件系统 ✅
- src/Events/PointerEvent.php（hover/down/move/up/wheel + 点击计数双/三击）、
  KeyboardEvent.php（Tab/Shift+Tab/Enter/Space + 扩展键）、FocusManager.php（Tab 顺序+回环+onChange）。
- Surface 接入：hover 高亮、键盘导航、Enter/Space 激活、焦点环绘制。
- tests/EventsTest.php（全绿）。

### Phase 9：语义/无障碍 ✅
- src/Semantics/WidgetRole.php（ARIA 角色枚举：button/checkbox/radio/slider/progressbar/
  textbox/combobox/group/list/tablist/tab/dialog/menuitem/image）。
- src/Semantics/SemanticsNode.php（fromLayout 构建语义树：role 来自 node.role 或
  spec.type() 映射；提升 label/value/checked/selected/focusable/几何）。
- LayoutNode 加 role 字段 + withRole() + semantics()。
- tests/SemanticsTest.php（4 passed）。

### Phase 10：Design Token 扩展 ✅
- DesignTokens 新增 color.washHover / washHoverLight / washDisabled / focusRing；
  stroke.hairline；focus.ringWidth/Gap；DARK 常量 + DesignTokens::dark()。
- 便捷访问：hoverWash()/disabledWash()/focusRing()/hairlineWidth()/focusRingWidth()/
  focusRingGap()；snapHairlineRect()（Retina 亚像素对齐，纯函数）。
- ButtonRenderer 消费 token：hovered/disabled 时叠加 wash（主题决定明暗，不再硬编码）。
- Surface.paintFeedback 改用 token（hover 跳过按钮避免重复；焦点环用 focusRing/宽度/间距）。
- tests/DesignTokensExtendedTest.php（4 passed）。

## 全量状态
- 84 passed，1 预存失败（CircleProgressBarTest::SIZE，Phase 1 旧问题，无关）。
- 自绘控件体系从「libui 容器死磕」升级为「单画布 + 自建布局 + 全自绘 + 事件/语义/Token」，
  与用户贴的 native SDK 架构完全对齐。

## 后续可选
- 把更多原生组件接到 Surface（list/table/dialog/tabs 已在语义层预留角色）。
- 让 Phase 7 控件也吃 token 的 hover/disabled wash（目前仅 ButtonRenderer 接入）。
- CircleProgressBarTest::SIZE 旧失败可顺手修掉（Phase 1 残留）。
