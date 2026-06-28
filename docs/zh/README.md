---
home: true
icon: home
title: 首页
heroImage: 
heroText: ui2
tagline: 一个基于 helgesverre/libui 的 PHP 原生桌面 GUI 工具包
actions:
  - text: 快速开始
    link: /zh/guide/
    type: primary

  - text: 查看 GitHub
    link: https://github.com/yangweijie/HelgeSverre-libui-sdk
    type: default

features:
  - title: 丰富的控件库
    details: 字段控件、选择器、对话框、自绘控件、树形/文件浏览器、代码编辑器、环形进度条等。
    icon: cube

  - title: 嵌入式 WebView
    details: 原生浏览器引擎 (WKWebView / WebKitGTK / WebView2) 内嵌到 libui 窗口，支持 JS↔PHP 桥接。
    icon: globe

  - title: 跨平台
    details: 支持 macOS、Linux 和 Windows，每个平台使用原生外观和体验。
    icon: desktop

  - title: 组合架构
    details: 通过 Composite 模式将多个基础控件组合成复杂控件，补丁层无缝集成上游容器。
    icon: layer-group

  - title: 补丁系统
    details: 无需 fork 上游库即可扩展——在 patches/ 中放置覆盖文件，安装时自动同步到 vendor/。
    icon: puzzle-piece

  - title: PHP 8.5+
    details: 专为现代 PHP 构建，使用 FFI、类型化属性、枚举、闭包和命名参数。
    icon: code

footer: MIT 协议
---
