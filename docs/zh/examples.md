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

## 打包为独立二进制程序

将您的 ui2 应用打包为独立的可执行文件（目标机器无需安装 PHP）：

### 前置条件

**macOS / Linux：**
```bash
# 1. 安装 static-php-cli 并构建 micro.sfx
composer install:spc

# 2. 确认 micro.sfx 已构建
ls ~/.spc/micro.sfx
```

**Windows：**
```batch
:: 安装 static-php-cli 并构建 micro.sfx
scripts\install-spc.bat

:: 确认
dir %USERPROFILE%\.spc\micro.sfx
```

> 下载 `static-php-cli` 并构建一个静态 PHP 解释器（`micro.sfx`），包含 FFI、PHAR 和 mbstring 扩展。一次性设置，编译 PHP 源码约需 10-30 分钟。
>
> **Windows 注意事项**：编译 PHP 源码需要 Visual Studio 2022（工作负载："使用 C++ 的桌面开发"）。需要 Windows 10 Build 17063+（内置 `curl.exe`）。

### 构建

```bash
# 构建 PHAR 归档（适用于任何项目）
composer build:phar -- examples/tetris.php --output=tetris.phar

# 构建独立二进制程序（需要 micro.sfx）
composer build:binary -- examples/tetris.php --name=Tetris --icon=icon.png

# 运行二进制程序
./dist/Tetris
```

构建流程：
1. **PHAR** — 打包应用代码、vendor 依赖和原生 `libui` 共享库
2. **二进制** — 将 `micro.sfx` + PHAR 拼接为单一可执行文件
3. **图标** — macOS：生成含 `AppIcon.icns` 的 `.app` 包；Linux：`.desktop` + PNG；Windows：通过 `rcedit` 注入 `.ico`

### 在依赖项目中使用

```bash
# 在依赖 yangweijie/ui2 的项目中：
php vendor/yangweijie/ui2/scripts/build-phar.php my-app.php --output=my-app.phar
php vendor/yangweijie/ui2/scripts/build-binary.php --phar=my-app.phar --name=MyApp
```

> **工作原理**：PHAR stub 在启动时将 `libui-ng` 共享库解压到临时目录（FFI 的 `dlopen()` 需要真实文件系统路径）。超过 7 天的旧解压文件会被自动清理。

### 原生库解压机制

运行时，打包后的二进制程序会：
1. 将 `libui` 共享库（`.dylib`/`.so`/`.dll`）解压到 `sys_get_temp_dir()`
2. 设置 `LIBUI_LIB` 环境变量，让 `Ffi::get()` 能定位到
3. 运行应用 — FFI 从真实文件系统加载原生库
4. 自动清理 7 天前的旧解压文件

### Composer 命令

| 命令 | 说明 |
|------|------|
| `composer build:phar -- <入口文件> [选项]` | 从 PHP 入口文件构建 PHAR |
| `composer build:binary -- <入口文件> [选项]` | 构建独立二进制程序 |
| `composer install:spc` | 安装 static-php-cli 并构建 micro.sfx |

### 脚本参考

| 脚本 | 说明 |
|------|------|
| `scripts/build-phar.php` | PHAR 归档构建器（打包应用 + vendor + 原生库） |
| `scripts/build-binary.php` | 二进制编排器（PHAR → micro.sfx → 图标 → .app/.exe） |
| `scripts/install-spc.sh` | static-php-cli 安装器 + micro.sfx 构建器 (macOS/Linux) |
| `scripts/install-spc.bat` | static-php-cli 安装器 + micro.sfx 构建器 (Windows) |
