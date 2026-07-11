# 进度日志 (Progress Log)

## 2026-07-01 — Tetris.app 闪退与内存泄漏修复

### Phase 29c: ✅ Tetris.app 闪退分析
- **问题 1**：启动闪退 "Cannot redeclare class Libui\Ffi" — PHP 8.5 `use FFI;` 语义变化 → 移除 `use FFI;`
- **问题 2**：关闭时 SIGTRAP — drawString 内存泄漏 + GC 不充分 → 显式 `free()` + 三次 GC
- **问题 3**：tokenizer 缺失 — 错误处理器崩溃 → 重建 micro.sfx 添加 tokenizer,filter

### Phase 29d: ✅ micro.sfx 重建
- SPC v3 构建：`ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter`
- 验证：`tokenizer:YES`, `filter:YES`

### Phase 29f: ✅ 补丁审查 + uiLabel 泄漏修复
- **发现 bug**：`markExternallyClosed()` 调用 `unset($this->handle)` 阻断 destroy 循环
- **修复**：保留 handle + `isExternallyClosed()` 守卫防双重释放

### Phase 29g-29h: ✅ tetris.php 清理
- 移除 onClosing 辅助代码（验证非必需）
- 删除 `$nextLabel`、局部 label 变量、冗余 `Ffi::init()`

### Phase 29i: ✅ 移除非必需补丁
- 删除 `patches/composer/ClassLoader.php`

### 文档更新
- `docs/{en,zh}/guide/patches.md` — 新增 Ffi/App/Control 条目
- `docs/{en,zh}/examples.md` — 扩展列表添加 tokenizer,filter
- `scripts/install-spc.bat` — SPC v3 语法 + tokenizer,filter

---

## 2026-07-07 晚（拖拽选牌功能）

### 本次完成
- [x] **拖拽连选/连消选牌**：重写 `onClick()` 支持长按左键拖拽。
  - 关键：libui `AreaMouseEvent` 拖拽移动时 `down=0` 但 `held` 保持按钮掩码 → 用 `down!==0 || held!==0` 判按住，`up!==0` 判松开。
  - 拖拽起点以首张牌当前状态决定模式：未选中 → 连选；已选中 → 连消。
  - `applyDrag()` 用 `dragTouched` 去重，单次拖拽每张牌只处理一次，避免闪烁。
  - 单点（按下+松开不移动）仍等价一次 toggle，向后兼容。
  - 移除死代码 `toggleSelect()`；`newGame()` 重置拖拽状态。
  - 新增 2 个 Pest 测试（拖拽连选/连消、单点 toggle）。**19 项全过**。php85 -l 通过。

## 2026-07-07 早（GUI 修复收尾 + 规划文件初始化）

### 本次完成
- [x] **日志乱码修复**（叫地主阶段）：`drawLog()` 中 `substr` → `mb_substr(..., 'UTF-8')`，截断 24→28 字符，按 UTF-8 字符边界切，彻底消除从汉字中间劈开产生的非法序列乱码（显示为 ♣♣ 等）。php85 -l 通过。
- [x] **初始化 planning-with-files 规划体系**：创建 `task_plan.md` / `findings.md` / `progress.md`，将前序多轮 GUI 修复（选将界面、主循环、AI 跟牌、卡牌 hit-test、结算居中、日志位置、托管功能、跨平台字体、窗口居中）系统归档。

### 验证
- `php85 -l src/Games/OnePieceDoudizhu/GameController.php` → No syntax errors
- 关键特性代码核验全部在位：mb_substr(L1261)、autoPlay(18处)、uiFontFamily(L33)、lastShownPlay(5处)、window->centered()(example L91)
- Pest 17 项测试（历史通过，本次未重跑因环境路径解析问题；纯数值/字符截断改动不影响逻辑）

### 待用户验证
- 运行 `php85 examples/onepiece-doudizhu.php` 截图确认：① 叫地主日志中文正常无乱码 ② 结算文字精确水平居中 ③ 日志不与出牌区/手牌重叠 ④ 托管延迟约 1s 且跨局自动取消

### 历史阶段回顾（已在 task_plan.md 汇总）
1. 核心设计 + 逻辑（Game.php）
2. 程序化 WAV 音效（scripts/gen-sfx.php）
3. GUI 框架 + 选将/对战界面
4. GUI 运行时多轮截图驱动修复（见 task_plan.md Errors 表）
5. 跨平台字体 + 窗口居中 + 按钮布局（重新选将置左、主按钮居中）
6. mb_substr 日志乱码修复（本次）

---

## 2026-07-11 — 三大子系统（图表 / 渲染引擎 / 布局引擎+自绘控件）

### 图表组件（Chart Component）
- **规划**：`.planning/2026-07-11-chart-component/`
- **10 阶段全部完成**：核心数据模型 → ChartRenderer / LineRenderer / BarRenderer / PieRenderer → Chart Area 组件 → chart-demo.php + 24 测试 → 拖拽交互修复 → 柱状标签/ tooltip / 主题 → tooltip 样式打磨 → 文档 → Color::lerp 主题动画 → tooltip 箭头 + 上色重构
- **测试**：24 项全部通过
- **未 commit**

### 渲染引擎（Rendering Engine）
- **规划**：`.planning/2026-07-11-rendering-engine/`（活跃中）
- **Phase 1**：RenderCommand / CommandExecutor / RenderCommandList + CircleProgressDelegate 提取到 Rendering 命名空间，8 测试
- **Phase 2**：DesignTokens 不可变主题系统 + CircleProgressBar / ToggleSwitch 主题化，6 测试
- **Phase 3**：WidgetRenderer 注册表（ButtonRenderer / CardRenderer）+ RendererButton 复合控件，12 测试
- **测试**：Phase 1-3 共 26 项全部通过
- **未 commit**

### 布局引擎 + 全自绘控件系统（Layout Engine + Full Custom Widget System）
- **规划**：`.planning/2026-07-11-layout-engine/`
- **Phase 4**：Flexbox 布局（LayoutStyle / LayoutNode / FlexLayout）16 测试
- **Phase 5**：Grid 布局（GridTrack / GridStyle / GridLayout）7 测试
- **Phase 6**：Surface 画布控件（1056 行）— 单一 Area 驱动 FlexLayout + RendererRegistry 绘制 + 鼠标路由
- **Phase 7**：扩展 WidgetRenderer（Checkbox / Radio / Slider / Progress / TextField / Select）9 测试
- **Phase 8**：事件系统（PointerEvent / KeyboardEvent / FocusManager）测试通过
- **Phase 9**：无障碍语义（WidgetRole / SemanticsNode）4 测试
- **Phase 10**：DesignTokens 扩展（hover/disabled wash、focus ring、hairline、DARK 主题）4 测试
- **额外控件**：BreadcrumbControl, ComboboxControl, DialogControl, DrawerControl, DropdownMenuControl, ListControl, PaginationControl, PopoverControl, ScrollViewControl, SearchFieldControl, SheetControl, TabControl, TableControl, TextAreaControl, RendererButton
- **示例**：renderer-button-demo.php, surface-controls-demo.php, surface-demo.php
- **修改**：CircleProgressBar.php / ToggleSwitch.php → DesignTokens；SeparatorLine.php → 移除 destructor；AGENTS.md → leak prevention 文档
- **未 commit**

### 修改的现有文件
- `CircleProgressBar.php` — DesignTokens 主题接入
- `ToggleSwitch.php` — DesignTokens 主题接入
- `SeparatorLine.php` — 移除 destructor（Composite GC 陷阱）
- `AGENTS.md` — 内存泄漏预防文档

---
*规划文件遵循 planning-with-files 技能：task_plan.md=阶段追踪，findings.md=技术发现，progress.md=会话日志。子工作流有独立的 `.planning/` 子目录规划文件。*
