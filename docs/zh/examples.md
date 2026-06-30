# 示例

从项目根目录运行示例：

```bash
php examples/all-components.php   # 完整的演示，6 个标签页展示所有控件
php examples/menu.php              # 声明式 vs 命令式菜单 API
php examples/webview.php           # 带侧边栏和 JS↔PHP 桥接的 WebView
php examples/tetris.php            # 完整的俄罗斯方块游戏，使用 Area 自绘实现
```

## all-components.php

在 6 个标签页中展示本包的所有控件：

1. **字段** — 所有输入字段类型
2. **自定义** — ToggleSwitch、StatusIndicator、CircleProgressBar
3. **对话框** — MessageBox、DialogConfirm、DialogPrompt、Toast
4. **选择器** — 颜色、字体、日期、时间选择器
5. **表格** — 使用 TableView 展示表格数据
6. **WebView** — TreeView 和 CodeEditor 启动按钮

## tetris.php

一个完整的俄罗斯方块游戏，完全使用 `Area` 自绘实现——无需外部游戏引擎或 canvas。展示了：

- **`Area` + `AreaDelegate`** — 自定义 2D 渲染 (`draw()`) 和键盘处理 (`key()`)
- **`Loop::repeat()`** — 重力定时器，速度随关卡递增
- **`DrawContext` 构建器** — 单元格 3D 斜面效果、阴影方块预览、网格线
- **键盘输入** — 方向键 (`ExtKey`) 控制移动/旋转，上下方向键软降/硬降
- **游戏机制** — 7 种方块、墙踢、消行、分数/等级/行数统计
- **叠加层** — 在 Area 上直接绘制暂停和游戏结束界面

```bash
php examples/tetris.php
```

操作：← → ↓ 移动，↑ 旋转，空格硬降，R 重新开始，Escape 暂停/继续。

## 测试文件

`examples/` 中的其他测试脚本：

| 脚本 | 功能 |
|---|---|
| `test-fields.php` | 字段控件测试 |
| `test-widgets.php` | 自定义控件测试 |
| `test-pickers.php` | 选择器对话框测试 |
| `test-circle-progress.php` | 环形进度条 |
| `test-treeview.php` | TreeView 控件 |
| `test-codeeditor.php` | CodeEditor 控件 |
| `test-tray.php` | 系统托盘 |
| `test-context-menu.php` | 上下文菜单 |
| `test-global-hotkey.php` | 全局快捷键 |
| `toast-test.php` | 通知测试 |
| `test-system-info.php` | 系统信息 |
| `test-log.php` | 日志查看器 |
| `test-process-util.php` | 进程工具 |
| `test-svg.php` | SVG 渲染 |
| `test-debug-bridge.php` | 桥接调试 |
| `test-set-icon.php` | 应用图标设置 |
| `tetris.php` | 完整俄罗斯方块游戏 — Area 自绘、键盘输入、重力定时器、阴影方块、计分系统 |
