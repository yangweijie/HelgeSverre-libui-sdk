# Task Plan: libui dylib 重建 + 示例文件

## Goal
1. 从上游正确 commit 重新编译 libui.dylib（macOS ARM64），应用 hugTrailing 补丁
2. 创建 control-gallery.php 和 grid.php 两个示例文件
3. 创建 tetris.php — 基于 libui Area 的完整俄罗斯方块游戏

## Current Phase
全部完成 ✅

## Phases

### Phase 1: Research & Diagnosis
- [x] 确认 dylib 来源：HelgeSverre/libui 依赖上游 43ba1ef，而非 kingbes/libui-ng
- [x] 确认 hugTrailing 修复方案：box.m vbox 返回 `[self nStretchy] == 0`
- [x] 验证 build system：meson + ninja
- **Status:** complete

### Phase 2: Rebuild libui.dylib
- [x] 从 43ba1ef 克隆 libui-ng
- [x] 应用 hugTrailing patch 到 /tmp/libui-ng/darwin/box.m
- [x] meson compile -C build (Release, shared, arm64)
- [x] nm 验证 315 个导出函数与原始 dylib 一致
- [x] 部署到 vendor/helgesverre/libui/lib/darwin/libui.dylib
- [x] FFI 加载验证 + test-fields-raw.php 确认无报错
- **Status:** complete

### Phase 3: control-gallery.php
- [x] 参考 libphp/examples/control_gallery.php
- [x] 左侧 Group Basic Controls: Button, Checkbox, Label, DateTimePicker×3, FontButton, ColorButton, Separator
- [x] 右侧 vbox: Numbers(Spinbox, Slider, ProgressBar), Lists(Combobox, EditableCombobox, RadioButtons), Tab(Page1-3)
- [x] 主布局 hbox(stretchy左, stretchy右), 600×500, 无菜单, App::new()
- [x] php85 -l 语法检查通过
- **Status:** complete

### Phase 4: grid.php
- [x] 3×3 Grid 对齐演示: hexpand+Fill/Center/End, xspan=2+Start, xspan=3+Fill
- [x] 480×320, setMargined(true)
- [x] php85 -l 语法检查通过
- **Status:** complete

### Phase 5: tetris.php
- [x] 7 种标准俄罗斯方块 (I/O/T/S/Z/J/L) + 旋转矩阵 + wall kick
- [x] Area 自绘制游戏板 (10×20) + 预览窗口
- [x] 键盘控制: 方向键移动/旋转, 空格硬降, Escape 暂停, R 重启
- [x] 幽灵方块 (ghost piece) 预览落点
- [x] 计分系统: 100/300/500/800×level 行清除, 软降+1, 硬降+2
- [x] 等级系统: 每 10 行升级, 重力加速
- [x] 3D 单元格斜坡效果 (bevel highlight/shadows)
- [x] Game Over / Pause 半透明覆盖层
- [x] PHP 8.5 运行无错误, 仅 vendor deprecation 噪音
- **Status:** complete

## Errors Encountered
| Phase | Error | Attempt | Resolution |
|-------|-------|---------|------------|
| 5 | `$params->width` undefined | 1 | Use `$params->areaWidth` (libui patched field name) |
| 5 | `FontDescriptor` class not found in `Libui\Draw\` | 1 | Import from `Libui\Text\FontDescriptor` |
| 5 | 底部方块被裁剪 | 1 | 窗口高度从 BOARD_H+30 → BOARD_H+90 (macOS 标题栏 ~50px) |
| 5 | 预览区域太窄 | 1 | 侧栏宽度 160 → 200 |
| 5 | 预览区域无边框 | 1 | 用 `Group::titled('NEXT', ...)` 包裹预览 Area |
| 5 | 预览方块尺寸不随区域缩放 | 1 | 动态 cell size: `min(20.0, (aw-12.0)/max(cols,rows))` |
| 5 | GAME OVER 文字偏左 | 1 | drawString x 从 10.0 → 0.0, width 从 BOARD_W-20 → BOARD_W |
