# Tetris.app 闪退与内存泄漏分析报告

## 概述

`dist/Tetris.app` 存在三个独立问题：启动闪退、错误处理器崩溃、关闭时 SIGTRAP。本文档详细分析每个问题的根本原因并提供修复方案。

---

## 问题 1：启动闪退 — "Cannot redeclare class Libui\Ffi"

### 症状

```
Fatal error: Cannot redeclare class Libui\Ffi (previously declared as local import)
  in phar://.../vendor/helgesverre/libui/src/Ffi.php on line 24
```

### 根本原因

**PHP 8.5 的 use 语句语义变化 + 类名大小写不敏感**

1. `Ffi.php` 原本有 `use FFI;` 语句（导入全局 `\FFI` 类到 `Libui` 命名空间）
2. PHP 8.5 将 `use` 视为"本地导入声明"（local import declaration）
3. PHP 类名大小写不敏感：`FFI` 和 `Ffi` 被视为相同
4. 因此 `use FFI;` 后声明 `class Ffi` → 冲突

PHP 8.5 源码位置：`Zend/zend_compile.c:9274-9280`

```c
if (FC(imports)) {
    zend_string *import_name =
        zend_hash_find_ptr_lc(FC(imports), unqualified_name);
    if (import_name && !zend_string_equals_ci(lcname, import_name)) {
        zend_error_noreturn(E_COMPILE_ERROR,
            "Cannot redeclare class %s (previously declared as local import)",
            ZSTR_VAL(name));
    }
}
```

### 修复状态

- ✅ **源码已修复**：`Ffi.php` 已移除 `use FFI;`，所有 FFI 引用使用 `\FFI` 全限定名
- ✅ **PHAR 已更新**：`dist/Tetris.phar` (Jul 1 00:31) 包含修复
- ✅ **Tetris.app 已重建**：本次分析中已用最新 PHAR 重新构建

### 验证

```
php85 dist/Tetris.phar     → ✅ 正常启动
micro.sfx + Tetris.phar    → ✅ 正常启动（游戏窗口打开）
```

---

## 问题 2：tokenizer 扩展缺失 — 错误处理器自身崩溃

### 症状

```
Fatal error: Uncaught Error: Call to undefined function NunoMaduro\Collision\token_get_all()
  in phar://.../vendor/nunomaduro/collision/src/Highlighter.php on line 155
```

### 根本原因

**SPC 构建 micro.sfx 时未包含 tokenizer 扩展**

`scripts/install-spc.sh` 第 219 行：

```bash
"${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo" --build-micro
```

编译的扩展列表缺少 `tokenizer`。

micro.sfx 实际加载的扩展：

| 扩展 | micro.sfx | 系统 php85 |
|------|-----------|------------|
| Core | ✅ | ✅ |
| FFI | ✅ | ✅ |
| Phar | ✅ | ✅ |
| mbstring | ✅ | ✅ |
| **tokenizer** | **❌ 缺失** | **✅** |
| filter | ❌ | ✅ |
| libxml | ❌ | ✅ |
| ... | (18 个) | (66 个) |

### 影响

- 当 PHP 运行时发生任何错误/异常时，`NunoMaduro\Collision\Highlighter` 试图用 `token_get_all()` 高亮源码
- `token_get_all()` 未定义 → 错误处理器自身崩溃
- **原始错误信息被掩盖**，用户只看到 "Call to undefined function token_get_all()"

### 修复方案

修改 `scripts/install-spc.sh`，在扩展列表中加入 `tokenizer`：

```bash
# 修改前
"${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo" --build-micro

# 修改后
"${SPC_BIN}" build "ffi,phar,mbstring,json,ctype,posix,fileinfo,tokenizer,filter" --build-micro
```

然后重新构建 micro.sfx 和 Tetris.app。

---

## 问题 3：关闭窗口时 SIGTRAP 崩溃 — libui 内存泄漏检测

### 症状

```
EXC_BREAKPOINT (SIGTRAP)
崩溃链: uiprivRealBug → uiprivDoUserBug → uiprivUninitAlloc → uiUninit
```

### 根本原因

**libui 的内存泄漏检测器在 `uiUninit()` 时发现未释放的内存**

libui 通过 `uiprivAlloc()` / `uiprivFree()` 跟踪所有内部内存分配（`darwin/alloc.m`）：

```objc
static NSMutableArray *allocations;  // 全局分配记录

void uiprivUninitAlloc(void) {
    if ([allocations count] == 0) {
        [allocations release];
        return;  // 无泄漏，正常退出
    }
    // 有泄漏 → 报告并 SIGTRAP
    uiprivUserBug("Some data was leaked; either you left a uiControl "
                  "lying around or there's a bug in libui itself. "
                  "Leaked data:\n%s", ...);
}
```

### 泄漏源分析

#### 源 A：drawString 中的 TextLayout / AttributedString（主要泄漏源）

`DrawContext::drawString()` 每次调用都创建临时对象：

```php
public function drawString(...): void {
    $string = new AttributedString();           // uiprivAlloc: uiAttributedString
    $string->append($text, Attribute::fromColor(...));
    $layout = new TextLayout($string, $font, ...); // uiprivAlloc: uiDrawTextLayout
    $this->text($layout, $x, $y);
    // 函数返回 → 期望 __destruct() 自动释放
}
```

`TextLayout::__destruct()` 调用 `uiDrawFreeTextLayout()`：
```php
public function __destruct() {
    $this->free();  // → Ffi::get()->uiDrawFreeTextLayout($this->layout)
}
```

**问题**：在 FFI 回调（Area draw handler）内创建的 PHP 对象，其 `__destruct()` 的执行时机依赖于 PHP GC。如果 GC 没有在 `uiUninit()` 之前回收这些对象，libui 的内存就会泄漏。

Tetris 的 draw 回调每次重绘调用 2-4 次 `drawString()`（score、level、lines、GAME OVER 文字），每次创建一个 TextLayout + AttributedString。游戏运行期间会触发多次重绘（定时器每 800ms-100ms 触发一次），累积大量未释放的 libui 内存。

#### 源 B：全局 $state 持有 widget 引用

```php
$state = new stdClass();
$state->scoreLabel = $scoreLabel;   // Libui\Label
$state->gameArea = $gameArea;       // Libui\Area
$state->previewArea = $previewArea; // Libui\Area
// ... 更多 widget 引用
```

`$state` 是全局变量，在 `App::run()` 的 finally 块和 `Ffi::uninit()` 中仍然存在。`gc_collect_cycles()` 无法回收这些 wrapper 对象。

虽然这些 widget 的 C handle 已被 libui 在窗口关闭时销毁，但 PHP wrapper 对象的 `__destruct()` 不会被调用（因为 `$state` 持有引用），所以如果有其他需要释放的资源（如 Area handler），也会泄漏。

#### 源 C：Loop::repeat 定时器

```php
// Loop.php 注释
// Cancellation is *lazy*: libui's uiTimer has no cancel call
```

libui 的 `uiTimer()` 没有 cancel API。定时器只能通过回调返回 false 来停止。在 `Ffi::uninit()` 中清除 retained closures 后，定时器回调不会再次执行，但 libui C 端的定时器结构可能仍然存在。

### 修复方案

#### 修复 1：drawString 显式释放（关键修复）

```php
// DrawContext.php - drawString 方法
public function drawString(...): void {
    $string = new AttributedString();
    $string->append($text, Attribute::fromColor(Color::from($color)));
    $layout = new TextLayout($string, $font, $width ?? 1.0e6, $align);
    $this->text($layout, $x, $y);

    // 显式释放，不依赖 __destruct()
    $layout->free();
    $string->free();
}
```

#### 修复 2：App::run() 添加清理钩子

```php
// App.php - run() 方法 finally 块
finally {
    // 1. 清除全局引用，让 GC 能回收 wrapper 对象
    //    （应用代码需要在 onClosing 中清除 $state 引用）

    // 2. 多次 GC 确保所有 __destruct() 都被执行
    gc_collect_cycles();
    gc_collect_cycles();  // 第二次：__destruct 可能触发更多引用释放

    foreach ($this->windows as $window) {
        try { $window->destroy(); } catch (\Throwable) {}
    }

    gc_collect_cycles();
    Ffi::uninit();
}
```

#### 修复 3：tetris.php 添加 onClosing 清理

```php
// tetris.php - 在 App::new() 之前添加
$window->onClosing(function () use ($state) {
    // 取消定时器
    if ($state->timerId !== null) {
        Loop::cancel($state->timerId);
        $state->timerId = null;
    }
    // 清除 widget 引用，让 GC 能回收
    $state->scoreLabel = null;
    $state->levelLabel = null;
    $state->linesLabel = null;
    $state->gameArea = null;
    $state->previewArea = null;
    return true;
});
```

---

## 修复优先级

| 优先级 | 问题 | 修复 | 状态 | 影响 |
|--------|------|------|------|------|
| P0 | 启动闪退 (use FFI 冲突) | 移除 `use FFI;` | ✅ 已修复 | 阻止应用启动 |
| P1 | drawString 内存泄漏 | 显式 `free()` | ✅ 已修复 | 关闭时 SIGTRAP |
| P1 | GC 不充分 (micro.sfx) | 三次 `gc_collect_cycles()` | ✅ 已修复 | 关闭时 SIGTRAP |
| P2 | tokenizer 缺失 | 重建 micro.sfx | ⏳ 需重建 | 错误信息不可见 |
| P2 | $state 引用泄漏 | onClosing 清理 | 📋 建议添加 | 辅助修复 |

## 最终验证结果

```
非 PHAR 模式 (php85 tetris.php):
  ✅ 启动正常
  ✅ 关闭无 SIGTRAP

PHAR 模式 (php85 Tetris.phar):
  ✅ 启动正常
  ✅ 关闭无 SIGTRAP

二进制模式 (micro.sfx + PHAR = Tetris.app):
  ✅ 启动正常 (游戏窗口打开)
  ✅ 关闭无 SIGTRAP (连续 2 次验证无 crash report)
```

## 实际应用的修复

### 修复 1：Ffi.php — 移除 `use FFI;`（已完成）
- 文件：`vendor/helgesverre/libui/src/Ffi.php` + `patches/`
- 将所有 FFI 引用改为 `\FFI` 全限定名

### 修复 2：DrawContext.php — drawString 显式释放（已完成）
- 文件：`vendor/helgesverre/libui/src/Draw/DrawContext.php` + `patches/`
- 在 `drawString()` 方法末尾添加 `$layout->free()` 和 `$string->free()`
- 不依赖 `__destruct()` 自动释放

### 修复 3：Ffi.php — 三次 GC（已完成）
- 文件：`vendor/helgesverre/libui/src/Ffi.php` + `patches/`
- 在 `uninit()` 中将单次 `gc_collect_cycles()` 改为三次
- 原因：micro.sfx 的 GC 不如 CLI 激进，需要多次 GC 确保所有 wrapper 对象被回收

### 修复 4：install-spc.sh — 添加 tokenizer（待执行）
- 文件：`scripts/install-spc.sh`
- 扩展列表添加 `tokenizer,filter`
- 需要重新构建 micro.sfx 才能生效

---

## 环境信息

- PHP 8.5.7 (micro SAPI, SPC 构建)
- libui-ng (commit 43ba1ef, macOS ARM64)
- macOS 26.5.1 (25F80)
- micro.sfx: 21.6 MB (18 个扩展)
- Tetris.phar: 16.3 MB (1954 文件)
- Tetris.app: 37.9 MB (micro.sfx + Tetris.phar)
