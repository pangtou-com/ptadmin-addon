# 指南概览

`PTAdmin/Addon` 负责低代码平台里的插件包管理，不负责具体业务模块本身。

当前建议的职责边界：

- 插件安装、卸载、升级、启停
- 插件包发现与 `manifest.json` 解析
- 运行期资源发现
- 指令、inject、hooks 的注册与调度

不建议放进插件管理器的内容：

- CMS、商城、支付中心等业务实现细节
- 某个业务模块内部的数据模型和业务流程
- 与插件协议无关的后台业务配置

如果你要开始开发插件，建议按这个顺序阅读：

1. [快速开始](/guide/quick-start.md)
2. [Manifest](/guide/manifest.md)
3. [生命周期](/guide/lifecycle.md)
4. [插件样板](/guide/plugin-starter.md)
