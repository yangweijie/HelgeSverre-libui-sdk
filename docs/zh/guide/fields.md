# 字段控件

每个 Field 都是一个 `Composite`，将 `Label` 与特定输入控件配对成水平行：

| 类名 | 输入控件 | 值类型 | 说明 |
|---|---|---|---|
| `TextField` | `Entry` | `string` | 简单文本输入 |
| `SearchField` | `Entry::search()` | `string` | 搜索样式字段 |
| `PasswordField` | `Entry::password()` | `string` | 密码输入，通过 `value()` 可读 |
| `NumberField` | `Spinbox` | `int` | 需要最小/最大值范围 |
| `SliderField` | `Slider` | `int` | 带有实时数值读取标签 |
| `FilePickerField` | `Entry`(只读) + `Button` | `string` | 需要父 Window，打开原生文件对话框 |
| `CheckboxField` | `Checkbox` | `bool` | 带标签的复选框 |
| `RadioGroup` | `RadioButtons` | `int` | 选中索引(0基)；`addOptions()` |
| `ComboBoxField` | `Combobox` | `int` | 选中索引(0基)；`addOptions()` |
| `EditableComboBoxField` | `EditableCombobox` | `string` | 可输入的下拉框；`addOptions()` |
| `DatePickerField` | `DateTimePicker` | `\DateTimeImmutable` | `dateOnly()`/`timeOnly()` 工厂方法 |
| `TextAreaField` | `MultilineEntry` | `string` | 垂直标签 + 可拉伸文本区域 |
| `ProgressBarField` | `ProgressBar` | (无) | `setProgress()`、`indeterminate()` |
| `SeparatorLine` | `Separator` | (无) | 细水平分隔线 |

```php
$field = new TextField('姓名:', '默认值');
$field->on('change', fn (string $val) => print($val));
$form->append($field->root(), '姓名:');

// 获取/设置值
$val = $field->value();
$field->setValue('新值');
```
