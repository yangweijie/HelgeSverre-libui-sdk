# 安装

## 环境要求

- **PHP ≥ 8.5**（需要 `ext-ffi`）
- 平台库：`libui-ng`（上游为 macOS、Linux、Windows 预编译）
- WebView 控件需要：通过 `composer install` 下载并编译 PebView 原生库

::: warning
上游要求 PHP 8.5+。PHP 8.4.x 在 `composer install` 时会失败。
:::

## 通过 Composer 安装

```bash
composer require yangweijie/ui2
```

`post-autoload-dump` 脚本会自动：
1. 将补丁应用到上游 vendor 文件（参见[补丁系统](/zh/guide/patches)）
2. 在 macOS 上从源码构建 PebView 原生库（需要 Xcode 命令行工具）

## 手动应用补丁

编辑 `patches/` 中的文件后，需要重新同步到 vendor/：

```bash
php patch.php
```

## 构建原生组件

```bash
# 构建 PebView 原生库
composer build:pebview

# 构建 WebView 桥接库（需要先完成 PebView）
composer build:bridge
```

## 构建独立可执行文件

你可以使用 **phpmicro** 将 PHP 应用打包为单个可移植的 `.exe`（Windows）或可执行文件（macOS/Linux）——phpmicro 是一个将 PHP 运行时嵌入到独立二进制中的微型运行时。

### 原理

1. **`build-phar.php`** — 将入口脚本、Composer 运行时依赖和平台相关的原生 DLL（libui、PebView）打包成 `.phar` 归档文件。PHAR 存根在启动时将原生库解压到临时目录，并设置 `LIBUI_LIB` 环境变量供 FFI 使用。
2. **phpmicro（`micro.sfx`）** — 自解压 PHP 运行时。将其与你的 `.phar` 拼接即可生成独立的可执行文件。

### 环境要求

- **PHP 8.5 CLI**（用于运行 `build-phar.php`）
- **phpmicro** — 从 [phpmicro 发布页](https://github.com/yangweijie/php-micro/releases) 下载 `php-micro.tar.gz` 并解压出 `micro.sfx`
- **仅 Windows**：[Microsoft Visual C++ Redistributable](https://aka.ms/vs/17/release/vc_redist.x64.exe)（`libui.dll` 和 `php_micro.dll` 依赖）

### 构建步骤

```bash
# 1. 构建 PHAR 归档
php scripts/build-phar.php examples/all-components.php --output=app.phar --name=MyApp

# 2. 与 micro.sfx 拼接生成独立可执行文件
copy /b micro.sfx + app.phar MyApp.exe

# 3. 运行
.\MyApp.exe
```

PHAR 存根会自动：

- 将原生 `.dll` / `.so` / `.dylib` 文件解压到 `sys_get_temp_dir()/ui2_<hash>/`
- 设置 `LIBUI_LIB` 环境变量，使 `Ffi::libPath()` 能找到正确的库文件
- 清理 7 天前的旧解压目录

### 重要说明

- **`uiInitOptions.Size`** — 框架的 `Ffi::init()` 已正确设置 `uiInitOptions` 的 `Size` 字段。这是 phpmicro 兼容性的关键：如果不设置，`uiInit()` 在 Windows 上会静默失败，事件循环正常运行但没有窗口出现。
- **事件循环** — Windows 上的 `uiMain()` 使用 `GetMessage()`，即使没有窗口也会阻塞。如果窗口没有出现，请检查 `Ffi::init()` 是否成功完成（`uiInit()` 失败时会返回错误字符串）。
- **临时目录权限** — PHAR 存根需要对 `sys_get_temp_dir()` 有写入权限。请确保运行用户有适当的权限。
