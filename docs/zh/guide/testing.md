# 测试

```bash
vendor/bin/pest
```

项目使用 **Pest 4**（基于 PHPUnit 12）。测试配置在 `phpunit.xml` 中。

## 测试结构

- `tests/Pest.php` — Pest 配置
- `tests/DialogsTest.php` — 通过反射测试上游私有方法（无需 FFI）

## 测试工具

- `Libui\Testing\CallbackSpy` — 无需事件循环的基于断言的回调验证
- `Libui\Testing\Inspect` — 控件检查助手
- `Window::resetMenuLock()` — 在测试中可在 Window 之后创建菜单

## 编写测试

使用 Pest 风格编写新测试：

```php
test('文本字段发射变更事件', function () {
    $field = new TextField('姓名:', '默认值');
    $spy = new CallbackSpy();
    $field->on('change', $spy);
    $field->setValue('新值');
    expect($spy)->toHaveBeenCalled();
});
```
