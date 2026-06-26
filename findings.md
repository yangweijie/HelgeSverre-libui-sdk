# Findings & Decisions — macOS Context Menu Black Background Fix

## Requirements
- macOS 右键上下文菜单弹出时背景应为正常的系统样式（浅色/深色主题适配）
- 无黑色背景
- 无忙光标
- 菜单在正确位置弹出
- 点击项目后执行对应回调
- 菜单自动关闭

## Research Findings

### Root Cause Analysis
1. **原始实现**：使用自定义 NSWindow + NSView/NSButton 手动构建菜单
2. **问题**：
   - `NSPopUpMenuWindowLevel` 下的自定义 NSWindow 渲染时背景变黑
   - 系统主题适配困难，需要手动处理深浅色模式
   - 事件循环管理复杂 (`runModalForWindow:`)，可能导致忙光标
3. **最佳方案**：使用系统原生 `NSMenu` API

### NSMenu Popup API
- `[NSMenu popUpMenuPositioningItem:atLocation:inView:]` — 标准右键菜单弹出方法
- 需要 NSView 作为宿主（通过临时 NSWindow 提供）
- 自动处理：渲染、动画、主题适配、高亮、事件循环
- 返回值为点击的菜单 item，或 nil（取消时）
- 同步调用，阻塞直到菜单关闭
- 每个菜单 item 可设 tag 用于识别

### NSMenu Event Context
- NSMenu 需要 NSEvent 上下文来处理鼠标跟踪
- 方法1：使用 `[NSApp currentEvent]`
- 方法2：创建临时 NSWindow 作为宿主，在 window 上调用
- 临时窗口方案更可靠

## Technical Decisions
| Decision | Rationale |
|----------|-----------|
| NSMenu + NSMenuItem 替代 NSView/NSButton | 原生菜单渲染，完美适配系统主题 |
| 临时 NSWindow 作为 NSMenu 宿主 | NSMenu 需要 NSView 上下文才能弹出 |
| NSMenuItem tag 做回调索引 | JSON 解析后按 tag 关联点击回调 |
| 同步阻塞式 API (popUpMenuPositioningItem) | 与 FFI 接口兼容，直接返回选中项索引 |

## Resources
- bridge/context_menu.m — macOS 桥接代码（需重写）
- bridge/context_menu.dylib — 编译后的二进制文件
- examples/test-context-menu-area.php — 测试脚本
- src/Widgets/ContextMenu.php — PHP ContextMenu 类
- Apple NSMenu docs: https://developer.apple.com/documentation/appkit/nsmenu

## Visual/Browser Findings
- 用户截图显示：右键菜单区域显示为纯黑色方块/矩形
- 忙光标问题已在前一轮修复
- 当前仅剩黑色背景问题未解决