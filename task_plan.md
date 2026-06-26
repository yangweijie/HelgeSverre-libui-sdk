# Task Plan: 添加全局快捷键支持

## Goal
为 PHP 桌面 GUI 应用添加系统级全局快捷键（Global Hotkey）支持，应用最小化或后台时也能响应快捷键。

## Current Phase
全部完成

## Phases

### Phase 0: 需求分析 & 方案设计
- [x] 调研 PebView 是否已提供快捷键 API
- [x] 确定平台方案（macOS Carbon RegisterEventHotKey / Linux XGrabKey / Windows RegisterHotKey）
- [x] 确定 PHP ↔ C 回调通信方案（polling via shared state）
- **Status:** complete

### Phase 1: 实现 macOS 桥接
- [x] 编写 bridge/hotkey.m（Carbon RegisterEventHotKey + EventHandlerUPP）
- [x] 编译 hotkey.dylib
- [x] 测试注册和响应用户按下的快捷键
- **Status:** complete

### Phase 2: 实现 PHP GlobalHotkey 类
- [x] 创建 src/System/GlobalHotkey.php
- [x] register($keyCombo, callable) / unregister() / unregisterAll()
- [x] 键盘组合解析（Cmd+Shift+A → cmdKey+shiftKey+kVK_ANSI_A）
- **Status:** complete

### Phase 3: 测试
- [x] 编写 examples/test-global-hotkey.php
- [x] 运行测试，确认全局快捷键生效
- **Status:** complete

### Phase 4: Linux / Windows 适配
- [x] bridge/hotkey_linux.c（X11 XGrabKey）
- [x] bridge/hotkey_win.c（Win32 RegisterHotKey）
- [x] GlobalHotkey.php 错误提示更新为三平台编译说明
- **Status:** complete

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| 使用 FFI + 独立 dylib 桥接（而非 PebView） | PebView 未提供快捷键 API；独立桥接更清晰 |
| macOS 使用 Carbon RegisterEventHotKey | 系统级 API，支持后台热键 |
| UI 事件池轮询（libui timer） | 无侵入方式集成到 libui 事件循环 |

## Notes
- macOS Carbon API 需要：`-framework Carbon`
- 编译命令：`clang -shared -fobjc-arc bridge/hotkey.m -framework Carbon -framework AppKit -o bridge/hotkey.dylib`