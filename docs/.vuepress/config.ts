import { defineUserConfig } from 'vuepress'
import { viteBundler } from '@vuepress/bundler-vite'
import { hopeTheme } from 'vuepress-theme-hope'

export default defineUserConfig({
  base: '/HelgeSverre-libui-sdk/',

  locales: {
    '/en/': {
      lang: 'en-US',
      title: 'ui2',
      description: 'A thin convenience layer over helgesverre/libui — native desktop GUI for PHP',
    },
    '/zh/': {
      lang: 'zh-CN',
      title: 'ui2',
      description: '一个基于 helgesverre/libui 的 PHP 原生桌面 GUI 工具包',
    },
  },

  bundler: viteBundler(),

  theme: hopeTheme({
    hostname: 'https://yangweijie.github.io/HelgeSverre-libui-sdk/',

    plugins: {
      icon: {
        assets: 'fontawesome-with-brands',
      },
    },

    locales: {
      '/en/': {
        navbar: [
          { text: 'Home', link: '/en/' },
          { text: 'Guide', link: '/en/guide/' },
          { text: 'Examples', link: '/en/examples' },
          {
            text: 'GitHub',
            link: 'https://github.com/yangweijie/HelgeSverre-libui-sdk',
          },
        ],

        sidebar: {
          '/en/guide/': [
            { text: 'Introduction', link: '/en/guide/' },
            { text: 'Installation', link: '/en/guide/installation' },
            { text: 'Quick Start', link: '/en/guide/quick-start' },
            { text: 'Architecture', link: '/en/guide/architecture' },
            { text: 'Fields', link: '/en/guide/fields' },
            { text: 'Dialogs', link: '/en/guide/dialogs' },
            { text: 'Pickers', link: '/en/guide/pickers' },
            { text: 'Widgets', link: '/en/guide/widgets' },
            { text: 'WebView', link: '/en/guide/webview' },
            { text: 'Patch System', link: '/en/guide/patches' },
            { text: 'Bridge System', link: '/en/guide/bridge' },
            { text: 'Drawing', link: '/en/guide/drawing' },
            { text: 'Menus', link: '/en/guide/menus' },
            { text: 'App Icon', link: '/en/guide/app-icon' },
            { text: 'Testing', link: '/en/guide/testing' },
          ],
          '/en/examples': [
            { text: 'Examples', link: '/en/examples' },
          ],
        },
      },

      '/zh/': {
        navbar: [
          { text: '首页', link: '/zh/' },
          { text: '指南', link: '/zh/guide/' },
          { text: '示例', link: '/zh/examples' },
          {
            text: 'GitHub',
            link: 'https://github.com/yangweijie/HelgeSverre-libui-sdk',
          },
        ],

        sidebar: {
          '/zh/guide/': [
            { text: '简介', link: '/zh/guide/' },
            { text: '安装', link: '/zh/guide/installation' },
            { text: '快速开始', link: '/zh/guide/quick-start' },
            { text: '架构', link: '/zh/guide/architecture' },
            { text: '字段控件', link: '/zh/guide/fields' },
            { text: '对话框', link: '/zh/guide/dialogs' },
            { text: '选择器', link: '/zh/guide/pickers' },
            { text: '自定义控件', link: '/zh/guide/widgets' },
            { text: 'WebView', link: '/zh/guide/webview' },
            { text: '补丁系统', link: '/zh/guide/patches' },
            { text: '桥接系统', link: '/zh/guide/bridge' },
            { text: '绘图', link: '/zh/guide/drawing' },
            { text: '菜单', link: '/zh/guide/menus' },
            { text: '应用图标', link: '/zh/guide/app-icon' },
            { text: '测试', link: '/zh/guide/testing' },
          ],
          '/zh/examples': [
            { text: '示例', link: '/zh/examples' },
          ],
        },
      },
    },
  }),
})
