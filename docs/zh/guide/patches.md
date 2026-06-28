# 补丁系统

此项目通过补丁层覆盖上游库的特定文件，无需 fork：

1. `patches/` 中的文件镜像 `vendor/` 下的路径结构
2. 在 `composer install` / `composer update` 时，`post-autoload-dump` 脚本（`patch.php`）将 `patches/` 中的所有内容递归复制到 `vendor/`
3. 这样你就可以扩展控件、添加方法或修复行为，而无需维护独立的 fork

## 当前已补丁的文件

位于 `patches/helgesverre/libui/src/` 下：

| 文件 | 新增内容 |
|---|---|
| `Box.php` | 接受 `Composite` 子元素；`horizontal()` 静态工厂；`appendStretchy()` |
| `Form.php` | 接受 `Composite` 子元素；HasValue 字段的 `values()`/`setValues()`；`appendStretchy()` |
| `Grid.php` | 接受 `Composite` 子元素；`appendAt()` 位置参数；`place()` 快捷方法 |
| `Group.php` | 接受 `Composite` 子元素；`titled()` 静态工厂 |
| `Tab.php` | 在 `append()`/`appendMargined()` 中接受 `Composite` 子元素 |
| `Menu.php` | 流式构建器 API；改进的 `MenuOrderException` |
| `MenuItem.php` | `onClick()` 替换处理器；`removeOnClick()`；错误处理器 |
| `Window.php` | `centered()` / `centeredOn()` 定位；`run()` 单窗口循环；`setWindowIcon()` |
| `Exception/MenuOrderException.php` | 携带锁定菜单的 Window 标题 |
| `Draw/DrawContext.php` | 流式构建器：`fillRect`、`strokeCircle`、`withSave()`、`drawString()` |
| `Draw/Path.php` | `wedge()`、`polygon()`、`ellipse()`、`roundedRect()`、`quadTo()`、`bezierThrough()` |
| `Draw/Params/AreaKeyEvent.php` | 语义查询方法 |
| `Draw/Params/AreaMouseEvent.php` | 语义查询方法 |

## 重要：仅追加

`patches/` 是**只追加的**——旧的补丁永远不会被移除。如果你从 `patches/` 中删除文件，之前的副本仍然存在于 `vendor/` 中。需要手动清理。

::: danger
不要直接编辑 `vendor/` 中的文件。将覆盖文件放在 `patches/` 中——下次安装时会自动同步。
:::
