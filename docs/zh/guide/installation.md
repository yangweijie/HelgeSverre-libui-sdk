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
