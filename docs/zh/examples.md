# 示例

从项目根目录运行示例：

```bash
php examples/all-components.php   # 完整的演示，6 个标签页展示所有控件
php examples/menu.php              # 声明式 vs 命令式菜单 API
php examples/webview.php           # 带侧边栏和 JS↔PHP 桥接的 WebView
php examples/tetris.php            # 完整的俄罗斯方块游戏，使用 Area 自绘实现
php examples/chart-v2-demo.php     # ChartV2 交互式图表示例
php examples/canvas-demo.php       # CanvasSpec 在 Surface 布局中嵌入自定义绘制
php examples/control-gallery.php   # 自绘控件画廊（所有基础 WidgetSpec）
```

## all-components.php

演示所有控件，分为 6 个标签页：

1. **字段** — 所有输入字段类型
2. **自定义** — ToggleSwitch、StatusIndicator、CircleProgressBar
3. **对话框** — MessageBox、DialogConfirm、DialogPrompt、Toast
4. **选择器** — 颜色、字体、日期、时间选择器
5. **表格** — TableView 表格数据
6. **WebView** — TreeView 和 CodeEditor

## tetris.php

完整的俄罗斯方块游戏，完全使用 `Area` 自绘实现：

- **`Area` + `AreaDelegate`** — 自定义 2D 渲染 `draw()`，键盘处理 `key()`
- **`Loop::repeat()`** — 重力计时器以递增速度驱动游戏
- **`DrawContext` 构建器** — 带 3D 浮雕效果的方块绘制、幽灵方块预览、网格线
- **键盘输入** — 方向键移动，上旋转，空格硬降，R 重开，Esc 暂停
- **游戏机制** — 7 种方块、踢墙、消行、计分/等级/行数追踪
- **覆盖层** — 暂停画面、游戏结束覆盖层直接绘制在 Area 上

```bash
php examples/tetris.php
```

## chart-v2-demo.php

基于 `ChartV2` 组件系统的交互式图表示例（`src/ChartV2/`）：

- **5 种图表类型** — 柱状、折线、面积、饼图、散点（顶部按钮切换）
- **动态数据** — "随机数据"按钮生成新的随机数据集
- **数值标签** — 一键开关数据点/柱上的数值
- **明/暗主题** — 一键切换
- **自定义配色** — 随机颜色重新着色所有系列
- **ChartWidget** — 将 ChartRenderer 封装在 AreaDelegate 中，支持鼠标悬停和 Tooltip

```bash
php examples/chart-v2-demo.php
```

## canvas-demo.php

演示 `CanvasSpec` — 在 Surface 的 `LayoutNode` 树中嵌入任意 `DrawContext` 绘制回调：

- **迷你折线图** — 使用 `fillPolygon`、`strokeLine`、`fillCircle` 自绘
- **迷你柱状图** — 多色柱使用 `fillRect` 渲染
- **动画进度条** — `Loop::repeat(50ms)` 驱动渐变色动画
- **与 LabelSpec 混合布局** — Canvas 叶子节点与文本标签共存于同一 LayoutNode 树

```bash
php examples/canvas-demo.php
```

核心 API：
```php
use Yangweijie\Ui2\Rendering\WidgetRenderer\CanvasSpec;

$canvas = new CanvasSpec(
    function (DrawContext $ctx, float $w, float $h): void {
        $ctx->fillRect(0, 0, $w, $h, Brush::rgb(0x1E293B));
        // 任意 DrawContext 绘制...
    },
    background: 0x1E293B,
);

$layout = LayoutNode::column()
    ->child(LayoutNode::leaf('header', new LabelSpec('标题'), height: 30.0))
    ->child(LayoutNode::leaf('chart', $canvas, height: 200.0));

$surface = new Surface($layout);
```

## control-gallery.php

经典 libui 控件画廊的自绘版本——演示所有基础 `WidgetSpec` 类型在 Surface 中渲染：

- **左栏**：Button（ButtonSpec）、Checkbox（CheckboxSpec）、Label（LabelSpec）、DatePicker（DatePickerSpec）、FontButton + ColorButton（通过 ButtonSpec 触发原生 picker）
- **右栏**：Number 输入框（NumberSpec）、Slider（SliderSpec）、Progress（ProgressSpec）、TextField（TextFieldSpec）、Radio 组（RadioSpec）、TabControl
- **事件**：按钮点击、复选框切换、滑块拖拽→进度同步、数字输入过滤、Radio 选择、标签页切换、字体/颜色选择器对话框

```bash
php examples/control-gallery.php
```

## renderer-button-demo.php

演示 `RendererButton`——一个桥接控件，底层包装 libui 原生 `Button`，但外观通过 `ButtonRenderer` 和 `DesignTokens` 绘制：

```bash
php examples/renderer-button-demo.php
```

展示：带圆角的主题按钮、悬停/按下状态、通过 `DesignTokens` 配置颜色主题、与 libui 原生 `Box` 布局集成。

## surface-demo.php

演示 `Surface` 画布控件——基于单个 libui `Area` 的可组合自绘控件：

```bash
php examples/surface-demo.php
```

展示：Surface 控件使用 `FlexLayout` 定位多个 `WidgetRenderer` 子控件（按钮、滑块、复选框），鼠标悬停/点击路由，以及命令批量渲染。

## surface-controls-demo.php

演示完整的 Surface 控件集：

```bash
php examples/surface-controls-demo.php
```

展示：ButtonControl、CheckboxControl、SliderControl、ProgressControl、TextFieldControl、SelectControl、ComboboxControl、TabControl 等——全部在 Surface 控件内渲染，支持主题切换。

## 测试文件

`examples/` 目录下的其他测试脚本：

| 脚本 | 功能 |
|---|---|
| `test-widgets.php` | 自定义控件测试 |
| `test-pickers.php` | 选择器对话框测试 |
| `test-circle-progress.php` | 圆形进度条 |
| `test-treeview.php` | TreeView 控件 |
| `test-codeeditor.php` | CodeEditor 控件 |
| `test-tray.php` | 系统托盘 |
| `test-context-menu.php` | 右键菜单（Area 和标准） |
| `test-global-hotkey.php` | 全局快捷键注册 |
| `toast-test.php` | Toast 通知 |
| `test-system-info.php` | 系统信息 |
| `test-log.php` | 日志查看器 |
| `test-process-util.php` | 进程工具 |
| `test-svg.php` | SVG 渲染 |
| `chart-v2-demo.php` | ChartV2 交互式图表（柱状/折线/面积/饼图/散点 + 主题 + 配色） |
| `canvas-demo.php` | CanvasSpec 在 Surface 布局中自定义绘制（折线图 + 柱状图 + 动画进度条） |
| `test-debug-bridge.php` | 桥接调试 |

## 打包为独立二进制文件

将你的 ui2 应用打包为独立可执行文件（目标机器无需安装 PHP）：

### 前置条件

**macOS / Linux:**
```bash
composer install:spc
```

**Windows:**
```batch
scripts\install-spc.bat
```

### 构建

```bash
# 构建 PHAR 归档
composer build:phar -- examples/tetris.php --output=tetris.phar

# 构建独立二进制文件（需要 micro.sfx）
composer build:binary -- examples/tetris.php --name=Tetris --icon=icon.png

# 运行
./dist/Tetris
```

### Composer 命令

| 命令 | 说明 |
|---------|-------------|
| `composer build:phar -- <entry> [options]` | 从 PHP 入口文件构建 PHAR |
| `composer build:binary -- <entry> [options]` | 构建独立二进制文件 |
| `composer install:spc` | 安装 static-php-cli 并构建 micro.sfx |
