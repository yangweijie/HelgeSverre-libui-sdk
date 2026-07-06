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

### Post-Phase 5: Docs Update
- [x] `docs/en/examples.md` — Add tetris.php to run list, full description section, test files table
- [x] `docs/zh/examples.md` — Same in Chinese
- **Status:** complete

### Phase 7b: Window::centered() 屏幕尺寸检测失败修复
- [x] `screenSizeWindows()` 在 Windows 上 PowerShell/wmic 均返回空
- [x] 添加 Win32 FFI `GetSystemMetrics` 作为首选检测方式
- **Status:** complete

### Phase 29c: Tetris.app 闪退与内存泄漏分析
- [x] 分析启动闪退 "Cannot redeclare class Libui\Ffi" — PHP 8.5 `use FFI;` 语义变化
- [x] 分析关闭时 SIGTRAP — libui 内存泄漏检测 (drawString 泄漏 + GC 不充分)
- [x] 分析 tokenizer 缺失 — 错误处理器崩溃掩盖原始错误
- [x] 修复 1：`Ffi.php` 移除 `use FFI;`，改用 `\FFI` 全限定名
- [x] 修复 2：`DrawContext.php` drawString 显式 `free()` 释放 TextLayout/AttributedString
- [x] 修复 3：`Ffi.php` uninit() 三次 `gc_collect_cycles()` 确保 wrapper 对象回收
- [x] 验证：PHAR 模式 + 二进制模式启动正常，关闭无 SIGTRAP
- [x] 待执行：`install-spc.sh` 添加 `tokenizer,filter` 并重建 micro.sfx
- [x] 建议添加：tetris.php `onClosing` 清理 $state 引用
- [x] 补丁审查：App.php 两次 GC 均必要（移除测试泄漏 uiLabel）
- [x] 修复 4：`Window.php` markExternallyClosed 不再 unset handle，`Control.php` __destruct 加守卫
- [x] 验证：移除 tetris.php onClosing 辅助代码后仍零泄漏
- [x] 清理：删除 `$nextLabel`、局部 label 变量、冗余 `Ffi::init()`
- [x] 清理：移除非必需的 `patches/composer/ClassLoader.php`
- **Status:** complete

### Phase 6: PHAR 打包系统设计
- [x] 调研 PHP → 二进制方案（BPC/FrankenPHP/static-php-cli/PHP embed SAPI）
- [x] 选定 phpmicro + PHAR SFX 方案（21MB micro.sfx，跨平台支持）
- [x] 解决 FFI dlopen 不支持 phar:// 的问题：PHAR stub 解压原生库到 /tmp
- [x] 确定打包 pipeline：entry → PHAR → micro.sfx concatenation → 平台打包
- **Status:** complete

### Phase 7: 脚本实现
- [x] `scripts/build-phar.php` — 只打包 require 运行时依赖（29/60+ packages），平台特定原生库过滤
- [x] `scripts/build-binary.php` — 全流程编排：PHAR build → locate micro.sfx → cat binary → 平台打包
- [x] `scripts/install-spc.sh` — SPC v3 安装 + micro.sfx 构建（含 China 网络适配）
- [x] `composer.json` — 添加 `build:phar`, `build:binary`, `install:spc` 命令
- **Status:** complete

### Phase 8: 文档
- [x] `docs/en/examples.md` — 添加 "Packaging as Standalone Binary" 章节
- [x] `docs/zh/examples.md` — 添加 "打包为独立二进制" 章节
- [x] 覆盖：依赖项目使用方式、原生库提取机制、命令参考
- **Status:** complete


### Phase 33: Windows Tetris.exe 窗口不显示修复 (micro.sfx uiInitOptions)
- [x] Diagnose: Tetris.exe event loop runs but window invisible
- [x] Isolate cause: uiInitOptions.Size not set → uiInit() silently fails under micro.sfx
- [x] Fix: Set opts->Size = sizeof(opts), check uiInit() return value
- [x] Verify: Window "Tetris" and "libui utility window" both appear on Windows
- [x] Patch both vendor/ and patches/ Ffi.php for persistence
- **Status:** complete

### Phase 34: macOS WebView EXC_BAD_ACCESS 崩溃修复 + Loop API
- [x] wvb_destroy() — 每步独立 @autoreleasepool 防止 ObjC use-after-free
- [x] test-treeview.php — 关闭流程改为 Loop::run() 后 destroy，而非 onClosing 内
- [x] src/WebView.php — cleanupOnClose() 注释警告单回调限制 + 推荐模式
- [x] patches/WebView.php — 将 WebView.php 移至 patches/ 系统
- [x] patches/helgesverre/libui/src/Loop.php — 新增 Loop API (defer/delay/repeat/cancel/run/stop)
- [x] patch.php — 代码格式化整理
- **Status:** complete

### Phase 38: SvgView 渐变引用 + CSS 继承 + 虚线支持
- [x] `collectGradients()` — 扫描 `//linearGradient|//radialGradient`（含 `<defs>` 内），解析 stop 颜色/offset/`stop-opacity` 与 `gradientUnits`，按 id 建注册表
- [x] `resolveBrush()` → `gradientBrush()` — `fill/stroke = url(#id)` 构建 libui `Brush::linearGradient/radialGradient`
- [x] 坐标空间：默认 `objectBoundingBox`（映射到元素 AABB）；`userSpaceOnUse` 用原始坐标
- [x] `collectStyles()` → `parseCss()` — 解析 `<style>` 规则，支持 类型 / `.class` / `#id` 选择器 + 后代组合器（空格拆链）
- [x] 级联顺序：继承 < 表现属性 < CSS 规则 < 内联 style；按 specificity **升序**排序（高优先级后应用生效）；根 `<svg>` 注入祖先链使 `svg path` 可匹配
- [x] `stroke-dasharray` / `stroke-dashoffset` → `StrokeParams(dashes, dashPhase)`；奇数列表按 SVG 规范补偶；`none` → 实线
- [x] `tests/SvgViewTest.php` 新增 19 个测试（共 40 passed）
- [x] `examples/test-svg.php` 新增「Gradient + CSS + Dash」按钮
- **Status:** complete

### Phase 39: SvgView 鼠标悬空 TypeError 修复
- [x] 症状：`php85 examples/test-svg.php` 抛 `Cannot assign null to property SvgDelegate::$hoveredIndex of type int`（SvgDelegate.php:992）
- [x] 根因：`hitTest()` 返回 `?int`（光标在空白处返回 null），但 `$hoveredIndex` 声明为 `int`，赋值 null → TypeError
- [x] 修复：`private ?int $hoveredIndex = null;`，两处重置（setPaths / mouseCrossed-leave）由 `-1` 改为 `null`
- [x] 悬停高亮判定 `if ($i === $this->hoveredIndex ...)` 对 null 安全（int !== null）
- [x] 验证：`php85 vendor/bin/pest tests/SvgViewTest.php` 40 passed 无回归
- **Status:** complete


# Errors Encountered

### Phase 29c (Tetris 闪退分析)
| Error | Attempt | Resolution |
|-------|---------|------------|
| "Cannot redeclare class Libui\Ffi" (PHP 8.5) | `use FFI;` 与 `class Ffi` 冲突 | 移除 `use FFI;`，改用 `\FFI` 全限定名 |
| SIGTRAP on close (内存泄漏检测) | drawString 创建 TextLayout/AttributedString 未释放 | 显式 `$layout->free()` + `$string->free()` |
| SIGTRAP on close (GC 不充分) | micro.sfx 单次 GC 不够 | 三次 `gc_collect_cycles()` 确保 wrapper 回收 |
| "Call to undefined function token_get_all()" | micro.sfx 缺 tokenizer 扩展 | install-spc.sh 添加 `tokenizer,filter`，已重建 micro.sfx |

### Phase 7 (PHAR 打包)
| Error | Attempt | Resolution |
|-------|---------|------------|
| PHAR build 51 分钟超时 | addFile() 逐文件调用 | 改用 `buildFromIterator(ArrayIterator)` **5.5s/3587 文件** |
| buildFromIterator 存储文件路径而非内容 | 早期 key/value 反转 bug | 用关联数组 `$files[phar_path] = fs_path` + `buildFromIterator($it, $base)` 正确传递 |

### Phase 7 (SPC 构建)
| Error | Attempt | Resolution |
|-------|---------|------------|
| SPC download URL 404 `spc-darwin-aarch64` | URL path wrong | OS 映射：`darwin → macos`，添加 `/nightly/` 路径 |
| Composer 包名错误 | `static-php/static-php-cli` | 改为 `crazywhalecc/static-php-cli` |
| `composer create-project` 到 `.` 失败 (非空目录) | 用子目录 | `./build-src` 子目录 |
| GitHub git clone phpmicro 超时 (China) | 多次尝试 | ghproxy + chsrc set git + `--dl-custom-git` |
| PHP configure: "Nothing to build" | WORKING_DIR 指向 ~/.spc | 设置 `WORKING_DIR/SOURCE_PATH/DOWNLOAD_PATH` 三个 env var |
| SPC 不自动复制 php-micro 到 sapi/micro/ | 不复制 | `cp -r source/php-micro source/php-src/sapi/micro` |
| SPC v3 语法错误 (`--with-php-version`) | v2 语法 | 改为 v3: `spc build "exts" --build-micro` |
| Phase | Error | Attempt | Resolution |
|-------|-------|---------|------------|
| 5 | `$params->width` undefined | 1 | Use `$params->areaWidth` (libui patched field name) |
| 5 | `FontDescriptor` class not found in `Libui\Draw\` | 1 | Import from `Libui\Text\FontDescriptor` |
| 5 | 底部方块被裁剪 | 1 | 窗口高度从 BOARD_H+30 → BOARD_H+90 (macOS 标题栏 ~50px) |
| 5 | 预览区域太窄 | 1 | 侧栏宽度 160 → 200 |
| 5 | 预览区域无边框 | 1 | 用 `Group::titled('NEXT', ...)` 包裹预览 Area |
| 5 | 预览方块尺寸不随区域缩放 | 1 | 动态 cell size: `min(20.0, (aw-12.0)/max(cols,rows))` |
| 5 | GAME OVER 文字偏左 | 1 | drawString x 从 10.0 → 0.0, width 从 BOARD_W-20 → BOARD_W |

### Phase 35 (patch.php 依赖兼容性修复)
| Error | Attempt | Resolution |
|-------|---------|------------|
| 作为 Composer 依赖安装时 patch.php 不执行 | composer scripts 只跑 root package | bootstrap.php (autoload.files) + .patches_applied 标记 |
| patch.php 硬编码 `patches/` `vendor/` 路径 | 用 __DIR__ 定位 patches | `getVendorDir()` 向上遍历 10 层找 vendor/ |
| 作为依赖时 __DIR__ 在 `vendor/yangweijie/ui2/` | 无 | `getVendorDir()` 从 vendor/yangweijie/ui2/ 向上 2 层到 project root 的 vendor/ |
| 每次请求都跑 patch.php 影响性能 | 无 | bootstrap.php 检查 .patches_applied 标记，存在则 skip |

### Phase 36 (SvgView 鼠标交互支持 + 测试)
| Error | Attempt | Resolution |
|-------|---------|------------|
| SvgArea 默认没有鼠标交互 | 无 | SvgDelegate: 添加 EmitsEvents trait, `hitTest()`, `mouse()`, `mouseCrossed()` |
| 悬停视觉反馈 | 无 | draw() 中非破坏性 hover highlight 叠加层 |
| 圆形/椭圆精确命中测试 | AABB 不够精确 | 圆: `dx²+dy² ≤ r²`; 椭圆: `(dx/rx)² + (dy/ry)² ≤ 1` |
| SvgDelegate 在单独文件中找不到 | 与 SvgView 同文件 | 测试中用 `require_once` 手动加载 |

### Phase 37 (SvgDelegate 拆分到独立文件)
| Error | Attempt | Resolution |
|-------|---------|------------|
| PSR-4 无法加载 SvgDelegate（与 SvgView 同文件） | 测试用 require_once | 提取独立 `src/Widgets/SvgDelegate.php`，PSR-4 自动加载正常，移除测试中的 require_once |

### Phase 38 (SvgView 渐变 / CSS / 虚线)
| Error | Attempt | Resolution |
|-------|---------|------------|
| TypeError: gradient coords `SimpleXMLElement` 不能传给 `(float)` | 1 | `frac()` 内 `(string)` 强制转换 stop offset / 坐标 |
| 后代选择器（如 `svg path`）不匹配 | 1 | 每个空格分隔的复合选择器独立 `parseCompoundSelector()`，整链全部匹配 |
| CSS 级联顺序错（高优先级规则被低优先级覆盖） | 1 | `usort` 由降序改为**升序**，高 specificity 后应用生效 |

### Phase 39 (SvgView hoveredIndex TypeError)
| Error | Attempt | Resolution |
|-------|---------|------------|
| `Cannot assign null to property $hoveredIndex of type int` | 1 | 属性改 `?int`，两处重置由 `-1` 改为 `null` |
