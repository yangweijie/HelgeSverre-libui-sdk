# Layout Engine — Findings

## libui 容器的根本局限（为什么必须自建布局）
- Area（scrolling/否）无任何 intrinsic 尺寸；裸放进 Box 会被压成滚动条/0。
- macOS 上 libui Group 宽度**只由标题文本宽度决定**，不采纳子 Area content size；
  空白标题（NBSP/em space/空串）一律测得 ~0 → 塌陷。可见文本标题能给宽度但
  产生「两张皮」/边框。结论：Group/Box 不可靠，弃用。
- 可靠自绘控件布局只有两条路（已验证）：
  1. 非滚动 Area + 父容器 stretchy（填满、无边框、宽度随容器）—— ToggleSwitch/
     StatusIndicator/RendererButton 现状。
  2. 自建布局引擎跑在单画布 Area 上（flex/grid）—— 本轮 Phase 4 起。

## FlexLayout 实现要点
- 单次遍历：basis(fixed>basis>0) → grow/shrink 分配 → justify 分布 → cross align → 递归。
- **固定 cross 尺寸优先于 stretch**：crossAxis() 的 stretch 分支用已解析的 child
  尺寸（无固定时已被设为 container），而非无条件返回 container。否则固定高度子节点
  会被拉伸/压扁。（首版踩坑：stretch 返回 container 导致固定高度变 0/容器高）
- shrink 用 `shrinkFactor * basis` 作权重（CSS-like），不是单纯 shrinkFactor。
- justify 的 spaceBetween/spaceAround/spaceEvenly 只在「有剩余且无 grow 消费」时生效。
- v1 不做内容测量（leaf 无 renderer measure）；叶子需 fixed size 或 grow。后续
  Phase 6 Surface 可给 leaf 接 renderer.measure() 算 content size。

## 测试
- tests/FlexLayoutTest.php 16 passed：row/column、gap/padding、grow、shrink、
  justify×3、align×3、嵌套、root rect。纯 headless。
- 全量除 CircleProgressBarTest::SIZE（Phase1 旧）外全绿；zend_mm_heap corrupted
  为退出期噪声（exit 0）。

## 后续衔接
- Surface（Phase 6）= 非滚动 Area + delegate：draw 跑 FlexLayout→遍历叶子调
  WidgetRenderer.render(spec,tokens,w,h)→CommandExecutor；mouse 按叶子 rect 命中
  → 路由 click/hover。RendererButton 可重写为 Surface 内的叶子节点，彻底摆脱
  libui 容器。
