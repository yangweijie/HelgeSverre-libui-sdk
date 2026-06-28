# 桥接系统

`bridge/` 目录包含平台特定的 C 源文件，用于将 PHP 连接到原生 WebView API：

| 平台 | 源文件 | 编译产物 |
|---|---|---|
| macOS | `bridge/webview_bridge.m` (Objective-C) | `webview_bridge.dylib` |
| Linux | `bridge/webview_bridge_linux.c` | `webview_bridge.so` |
| Windows | `bridge/webview_bridge_win.c` | `webview_bridge.dll` |

桥接库通过 FFI 加载，负责创建、移动和销毁托管原生 WebView 的子窗口。

## 构建桥接库

```bash
# 先构建 PebView 原生库
composer build:pebview

# 然后构建 WebView 桥接库
composer build:bridge
```

## Dock 图标（macOS）

桥接库还提供了 `wvb_set_dock_icon()` 函数，在 macOS 上调用 `[NSApp setApplicationIconImage:]` 以设置 Dock/任务栏图标，由 `Window::setWindowIcon()` 内部使用。
