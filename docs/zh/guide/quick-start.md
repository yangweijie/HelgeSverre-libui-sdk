# 快速开始

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Libui\Build;
use Yangweijie\Ui2\Fields\TextField;

Ffi::init();

// 一个带标签的文本输入框，支持变更事件
$name = new TextField('姓名:', '世界');
$name->on('change', fn (string $val) => print("你好, {$val}!\n"));

$window = new Window('ui2 演示', 400, 200);
$window->setMargined(true);
$window->setChild(
    Build::vbox(
        $name,
        new Label('在上方输入框中输入文字'),
    ),
);
$window->run();
```

## 关键模式

- 在任何控件构造前调用 `Libui\Ffi::init()`（幂等操作）
- 多窗口应用使用 `Libui\App::run()` 替代 `Window::run()`
- 事件回调返回 `void`；在回调内部使用 try/catch 处理异常
- 传递给 libui 回调的闭包会被框架自动持有——无需手动保持引用
