# 菜单

两种 API 并存。菜单**必须**在第一个 `Window` 之前创建（运行时通过 `MenuOrderException` 强制执行）。

## 声明式 / 流式风格

```php
Menu::create('文件')
    ->item('打开', fn (MenuItem $item) => /* ... */)
    ->separator()
    ->quitItem();

Menu::create('编辑')
    ->checkItem('暗色模式', fn (MenuItem $item) => /* ... */);
```

## 命令式风格

```php
$help = new Menu('帮助');
$about = $help->appendAboutItem();
$about->onClick(fn (MenuItem $item) => /* ... */);
```

::: note
经过补丁的 `MenuItem::onClick()` 每次调用会替换处理器——不像大多数 libui 回调那样堆叠。
:::
