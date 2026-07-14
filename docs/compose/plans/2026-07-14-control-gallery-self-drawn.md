# Control Gallery 自绘改造实施计划

> **For agentic workers:** Use compose:execute to implement this plan task-by-task.

**Goal:** 将 `examples/control-gallery.php` 从 100% 原生 libui 控件改造为 Surface 自绘控件，展示 GridSpec、NumberSpec 和所有基础自绘组件。

**Architecture:** 单个 Surface + LayoutNode 树布局，所有控件通过 WidgetSpec 渲染。Grid 区域使用 LayoutNode 的 gridCol/gridRow/colSpan/rowSpan 属性。FontButton 和 ColorButton 无自绘替代，保留为自绘 ButtonSpec 触发原生 picker。

**Tech Stack:** PHP 8.5, Surface, LayoutNode, WidgetSpec, GridStyle

## Global Constraints

- PHP ≥ 8.5, ext-ffi required
- `never edit vendor/` — use patches/
- Write tests in Pest style
- Run `php85 -l` to verify syntax before testing

---

## 文件变更

| 操作 | 文件 | 说明 |
|------|------|------|
| 修改 | `examples/control-gallery.php` | 从原生 libui 改造为 Surface 自绘 |
| 不变 | `examples/grid.php` | 保留原版作为原生 Grid 对照 |
| 不变 | `examples/surface-controls-demo.php` | 已覆盖大部分自绘控件 |

---

### Task 1: 改造 control-gallery.php 左侧面板 — 基础控件

**Files:**
- Modify: `examples/control-gallery.php`

**映射关系：**

| 原生控件 | 自绘替代 | Spec 类型 |
|----------|----------|-----------|
| Button | ButtonSpec | `button` |
| Checkbox | CheckboxSpec | `checkbox` |
| Label | LabelSpec | `label` |
| DateTimePicker | DatePickerSpec | `date_picker` |
| FontButton | ButtonSpec (触发原生 picker) | `button` |
| ColorButton | ButtonSpec (触发原生 picker) | `button` |
| Separator | LabelSpec (水平线) | `label` |

- [ ] **Step 1:** 重写文件头部注释和 import。移除所有 `Libui\*` 原生控件 import（保留 `Libui\App`、`Libui\Window`、`Libui\Build`、`Libui\Label` 用于外壳）。添加 Surface 相关 import。

- [ ] **Step 2:** 重写左侧面板。用 `LayoutNode::column()` + `LayoutNode::leaf()` + 对应 Spec 替换所有原生控件。保留原生 `Button` + `Label` 用于 FontButton/ColorButton 触发区域（因为无自绘替代）。

- [ ] **Step 3:** 运行 `php85 -l examples/control-gallery.php` 验证语法。

### Task 2: 改造右侧面板 — Numbers/Lists/Tab

**Files:**
- Modify: `examples/control-gallery.php`

| 原生控件 | 自绘替代 | Spec 类型 |
|----------|----------|-----------|
| Spinbox | NumberSpec | `number_field` |
| Slider | SliderSpec | `slider` |
| ProgressBar | ProgressSpec | `progress` |
| Combobox | SelectSpec | `select` |
| EditableCombobox | TextFieldSpec | `text_field` |
| RadioButtons | RadioSpec (逐个) | `radio` |
| MultilineEntry | TextAreaControl | `text_area` |
| Tab | TabControl | — |

- [ ] **Step 4:** 重写右侧面板。Numbers 用 NumberSpec + SliderSpec + ProgressSpec。Lists 用 SelectSpec + RadioSpec。Tab 用 TabControl。

- [ ] **Step 5:** 运行 `php85 -l examples/control-gallery.php` 验证语法。

### Task 3: 事件接线 + 运行验证

**Files:**
- Modify: `examples/control-gallery.php`

- [ ] **Step 6:** 为所有交互控件接线事件（`surface->onClick()`、`surface->onDrag()` 等）。Slider 拖拽更新 Progress，Radio 点击切换选中态，Number 输入过滤为数字。

- [ ] **Step 7:** 运行 `php85 examples/control-gallery.php` 验证全部控件正常显示和交互。

### Task 4: 更新文档

- [ ] **Step 8:** 更新 `docs/en/examples.md` 和 `docs/zh/examples.md` 中 control-gallery.php 的描述。

---

## 控件尺寸参考（来自 surface-controls-demo.php）

- Button: width 100-140, height 36
- Checkbox: width 180, height 32
- Slider: width 220, height 32
- Radio: width 160, height 28
- Progress: width 240, height 22
- NumberSpec: width 280, height 34
- SelectSpec: width 280, height 34
- TextFieldSpec: width 280, height 34
- LabelSpec: height 20-28

## Grid 布局参考

```php
// GridSpec 是纯布局容器，无视觉渲染
// 用 LayoutNode 的 gridCol/gridRow/colSpan/rowSpan 控制位置
$grid = LayoutNode::column(width: 480, height: 300, align: 'start')
    ->child(LayoutNode::leaf('btn1', new ButtonSpec('Fill'), gridCol: 0, gridRow: 0, width: 140, height: 36))
    ->child(LayoutNode::leaf('btn2', new ButtonSpec('Center'), gridCol: 1, gridRow: 0, width: 140, height: 36));
```
