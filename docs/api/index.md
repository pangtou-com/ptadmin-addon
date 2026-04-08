# API 概览

文档分为三部分：

- [命令行 API](/api/commands.md)
- [Facade API](/api/facade.md)
- [能力接口](/api/contracts.md)
- [运行期注册 API](/api/runtime.md)

最常用的入口是：

- 命令：`addon:init`、`addon:install-local`、`addon:install`、`addon:upgrade`
- Facade：`Addon::executeInject($group, $code, $payload, $action)`、`Addon::triggerHook()`、`Addon::getDirectives()`
- 运行期：`BaseInstaller`、`BaseBootstrap`
