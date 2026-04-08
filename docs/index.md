---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "PTAdmin/Addon"
  tagline: 面向低代码平台的插件管理器
  actions:
    - theme: brand
      text: 快速开始
      link: /guide/quick-start
    - theme: alt
      text: API 文档
      link: /api/index
    - theme: alt
      text: GitHub
      link: https://github.com/pangtou-com/ptadmin-addon

features:
  - title: 清晰协议
    details: 统一使用 manifest、Installer、Bootstrap 三层协议描述插件。
  - title: 运行期注册
    details: directives、inject、hooks 全部通过代码注册，便于 IDE 跳转和重构。
  - title: 安装与回滚
    details: 支持云端安装、本地 zip 安装、升级、启停、安装失败回滚。
---
