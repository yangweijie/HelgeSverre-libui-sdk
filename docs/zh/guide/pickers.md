# 选择器

用于选取值的模态对话框。都使用**嵌套事件循环步骤**（`uiMainStep(1)`）——它们不会调用 `uiQuit()`，因此可以在已运行的 `uiMain()` 循环中调用。所有选择器接受可选的父 Window 参数以居中显示。

| 类名 | 返回值 | 说明 |
|---|---|---|
| `ColorPickerDialog` | `?Color` | 在临时模态窗口中封装 `ColorButton` |
| `FontPickerDialog` | `?FontDescriptor` | 在临时模态窗口中封装 `FontButton` |
| `DatePickerDialog` | `?\DateTimeImmutable` | 仅日期选择器（无时间） |
| `TimePickerDialog` | `?\DateTimeImmutable` | 仅时间选择器（无日期） |

```php
$color = ColorPickerDialog::pick($mainWindow);
if ($color !== null) {
    // 使用颜色
}

$font = FontPickerDialog::pick($mainWindow);
if ($font !== null) {
    // 使用字体描述符
}

$date = DatePickerDialog::pick($mainWindow);
$time = TimePickerDialog::pick($mainWindow);
```
