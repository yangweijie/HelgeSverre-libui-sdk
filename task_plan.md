# Task Plan: 修复 macOS 右键菜单黑色背景问题

## Goal
修复 macOS 右键上下文菜单弹出时背景显示为黑色的问题，使其使用系统原生样式正常渲染。

## Current Phase
全部完成

## Phases

### Phase 1: ✅ Requirements & Discovery
- [x] 确认问题：macOS 右键菜单弹出时背景为黑色
- [x] 分析 bridge/context_menu.m 代码
- [x] 确定根因：自定义 NSWindow 方式渲染异常，需改用原生 NSMenu API
- **Status:** complete

### Phase 2: ✅ Solution Design
- [x] 确定使用 NSMenu 的 popUpMenuPositioningItem:atLocation:inView: 方法
- [x] 通过临时不可见窗口提供 NSEvent 上下文
- [x] 移除复杂的自定义 NSView/NSButton 渲染逻辑
- **Status:** complete

### Phase 3: ✅ Implementation
- [x] 重写 bridge/context_menu.m 使用 NSMenu + 临时窗口方案
- [x] 编译新的 context_menu.dylib
- [x] 运行测试验证
- **Status:** complete

### Phase 4: ✅ Testing & Verification
- [x] 运行 test-context-menu-area.php 测试右键菜单
- [x] 确保无黑色背景、无忙光标、功能正常
- [x] 修复：右键仅响应蓝色矩形区域内（添加坐标范围判断 x:20~220, y:20~170）
- **Status:** complete

### Phase 5: ✅ Delivery
- [x] 更新进度文件
- [x] 总结修复内容
- **Status:** complete

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| 使用 NSMenu 代替自定义 NSWindow | NSMenu 是 macOS 原生右键菜单 API，自动处理渲染、动画、位置、光标和事件循环 |
| 使用临时不可见 NSWindow 提供 menu 上下文 | NSMenu 的 popUpMenuPositioningItem 需要在 NSView 上调用，临时窗口作为宿主 |
| 移除 NSView/NSButton 自定义渲染 | NSMenu 自动处理样式、高亮、禁用状态，无需手动绘制 |
| 使用 NSEvent 的 mouseLocation 定位 | 更精确的鼠标位置获取方式 |
| 仅矩形区域内响应右键 | Area mouse 事件覆盖整个控件区域，需按绘制区域做坐标过滤 |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| 黑色背景 | 1 | 将 windowLevel 从 CGShieldingWindowLevel 改为 NSPopUpMenuWindowLevel |
| 黑色背景 | 2 | 重写使用 NSMenu 原生 API，完全移除自定义 NSWindow 渲染 |
| 矩形外也能右键出菜单 | 1 | 在 mouse() 中添加 x:20~220, y:20~170 坐标范围判断 |

## Notes
- 编译命令：`cd bridge && clang -shared -fobjc-arc context_menu.m -framework Foundation -framework AppKit -o context_menu.dylib`
- 测试命令：`rm -f ~/.tmp/ctxmenu.log && php85 examples/test-context-menu-area.php`
- 日志用法：`php85 -r 'require "vendor/autoload.php"; \Yangweijie\Ui2\Logging\Log::info("hello"); Log::flush();'`