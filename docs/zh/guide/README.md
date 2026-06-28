# 简介

**yangweijie/ui2** 是 [`helgesverre/libui`](https://github.com/HelgeSverre/libui) 的薄封装层——通过 FFI 实现 PHP 原生桌面 GUI。

在上游的类型化控件类、自定义 2D 绘图、表格、菜单和对话框基础上，本包增加了组合控件、字段助手、选择器对话框、自绘控件、嵌入式 WebView 引擎、树形/文件浏览器、代码编辑器和环形进度条。

## 项目结构

| 路径 | 说明 |
|------|------|
| `src/` | 你的代码 — `Yangweijie\Ui2\` 命名空间 |
| `src/Composite.php` | 多控件组合的抽象基类 |
| `src/EmitsEvents.php` | 事件发射器 Trait |
| `src/Fields/` | 标签+输入组合（TextField、NumberField、CheckboxField 等） |
| `src/Pickers/` | 模态选择器对话框（颜色、字体、日期、时间） |
| `src/Dialogs/` | 消息框、确认对话框、输入对话框 |
| `src/Widgets/` | 自绘控件：ToggleSwitch、StatusIndicator、CircleProgressBar、Toast、TableView、TreeView、CodeEditor |
| `src/Layout/` | TabContainer、GroupSection 便捷封装 |
| `src/WebView.php` | 嵌入式浏览器（通过无边框子窗口实现） |
| `assets/` | WebView 控件的 HTML/JS 资源 |
| `patches/` | 上游覆盖文件（安装时同步到 vendor/） |
| `bridge/` | WebView 子窗口桥接的 C/ObjC 源码 |
| `bootstrap.php` | 通过 composer autoload 自动加载 — 注册 Collision 错误处理器 |
