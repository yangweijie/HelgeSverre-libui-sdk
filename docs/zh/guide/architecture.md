# 架构

## Composite 模式

核心抽象是 `Composite`——一个由多个控件组合而成的抽象基类。`Composite` 将多个子控件封装在 `root()` 方法之后，整个组合可以像单个控件一样添加到容器（`Box`、`Form`、`Grid`）中。

```php
abstract class Composite implements HasValue
{
    abstract public function root(): Control;
    public function value(): mixed { /* 在子类中覆盖 */ }
    public function setValue(mixed $value): static { /* 覆盖 */ }
}
```

所有容器补丁（`Box`、`Form`、`Grid`、`Group`、`Tab`）都能透明地接受 `Composite` 子元素——它们在内部调用 `$composite->root()`。

## EmitsEvents Trait

一个轻量级事件发射器 trait。将其加入任何类即可使用 `on(event, handler)` / `emit(event, data)`。

```php
class MyWidget extends Composite
{
    use EmitsEvents;

    public function doSomething(): void
    {
        $this->emit('change', $this->value());
    }
}

$widget->on('change', fn ($val) => print("Changed: {$val}"));
```

所有 Field 组合控件都使用此 trait，在输入值变化时发射 `'change'` 事件。

## 重要：Composite GC 陷阱

临时的 `Composite` 对象（如 `(new SeparatorLine())->root()`）会在语句结束时被 PHP 的 GC 调用 `__destruct()`，进而调用 `uiControlDestroy()` 销毁底层 C 控件，而此时 libui 仍持有对该控件的引用。**始终将 Composite 存储在命名的持久变量中。**

如果遇到 `uiControlVerifySetParent` 错误，原因即在于此。
