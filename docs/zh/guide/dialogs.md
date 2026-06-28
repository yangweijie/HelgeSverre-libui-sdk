# 对话框

| 类名 | 说明 |
|---|---|
| `MessageBox` | 静态助手：`info()`、`warning()`、`error()` — 封装上游原生 msgBox API |
| `DialogConfirm` | `ask(Window, $title, $message): bool` — 模态是/否对话框 |
| `DialogPrompt` | `ask(Window, $title, $label, $default): ?string` — 模态文本输入对话框 |

所有模态对话框都接受可选的父 Window 参数；指定后对话框会居中于父窗口而非屏幕中央。

```php
use Yangweijie\Ui2\Dialogs\MessageBox;
use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;

MessageBox::info($window, '已保存', '文档保存成功。');

$confirmed = DialogConfirm::ask($window, '删除', '确定要删除吗？');
if ($confirmed) {
    // 执行删除
}

$name = DialogPrompt::ask($window, '输入', '请输入你的姓名:', '张三');
if ($name !== null) {
    print("你好, {$name}!");
}
```
