# 示例

从项目根目录运行示例：

```bash
php examples/all-components.php   # 完整的演示，6 个标签页展示所有控件
php examples/menu.php              # 声明式 vs 命令式菜单 API
php examples/webview.php           # 带侧边栏和 JS↔PHP 桥接的 WebView
```

## all-components.php

在 6 个标签页中展示本包的所有控件：

1. **字段** — 所有输入字段类型
2. **自定义** — ToggleSwitch、StatusIndicator、CircleProgressBar
3. **对话框** — MessageBox、DialogConfirm、DialogPrompt、Toast
4. **选择器** — 颜色、字体、日期、时间选择器
5. **表格** — 使用 TableView 展示表格数据
6. **WebView** — TreeView 和 CodeEditor 启动按钮

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
