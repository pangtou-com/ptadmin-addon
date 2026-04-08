# 模板指令注册规范

## 目标

本规范用于约束插件模板指令的注册方式，目标是替代当前通过配置文件声明指令处理器的方式。

核心方向：

- 指令通过代码注册
- 指令定义可被 IDE 直接定位
- 编译器与执行器共享同一套指令元信息
- 不再依赖 `json` 配置中的 `directives` 字段

## 为什么不建议继续用 JSON 配置指令

当前配置式指令虽然实现简单，但有几个明显问题：

- 不利于 IDE 跳转和全局搜索
- 不利于重构时同步修改类名和方法名
- 类型、方法、缓存策略都只能靠运行期校验
- 编译期与执行期依赖字符串配置，维护成本高

因此，模板指令更适合改为代码注册。

## 设计原则

- 指令定义必须显式注册
- 指令处理器必须是代码类，而不是配置字符串拼装
- 指令类型必须显式声明
- 指令注册入口建议统一放在插件 `Bootstrap`
- 插件停用时应允许反注册

## 指令定义最小模型

一个模板指令至少应包含以下信息：

- `name`：指令名称
- `handler`：处理器类
- `method`：执行方法
- `type`：指令类型
- `cacheable`：是否允许缓存

一期建议支持的类型：

- `loop`
- `output`
- `if`

## 推荐注册方式

推荐在插件 `Bootstrap` 中集中注册：

```php
<?php

namespace Addon\Cms;

use Addon\Cms\Directive\ListsDirective;
use PTAdmin\Addon\Contracts\AddonBootstrapInterface;
use PTAdmin\Addon\Contracts\BootstrapContextInterface;
use PTAdmin\Addon\Support\DirectiveDefinition;

final class Bootstrap implements AddonBootstrapInterface
{
    public function boot(BootstrapContextInterface $context): void
    {
        $context->directives()->register(
            DirectiveDefinition::make('lists')
                ->handler(ListsDirective::class)
                ->method('handle')
                ->type('loop')
                ->cacheable(true)
        );
    }

    public function shutdown(BootstrapContextInterface $context): void
    {
        $context->directives()->unregister('lists');
    }
}
```

## 处理器建议

建议每个指令使用独立处理器类。

示意：

```php
<?php

namespace Addon\Cms\Directive;

use PTAdmin\Addon\Service\DirectivesDTO;

final class ListsDirective
{
    public function handle(DirectivesDTO $dto): array
    {
        return [];
    }
}
```

建议：

- 默认公开方法统一使用 `handle`
- 复杂指令不要堆在同一个服务类中
- 指令处理器可以通过容器注入服务依赖

## 编译器侧需要的能力

编译器在解析模板指令时，至少需要以下能力：

- 判断指令是否存在
- 根据名称和方法获取指令定义
- 判断指令类型
- 为执行器提供处理器信息

因此，指令注册中心建议同时服务于：

- `Compiler`
- `DirectiveActuator`
- 后台调试或展示界面

## 注册中心建议职责

指令注册中心至少应提供：

- 注册指令
- 反注册指令
- 判断指令是否存在
- 获取指令定义
- 判断是否为循环指令

示意接口：

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface DirectiveRegistryInterface
{
    public function register(DirectiveDefinitionInterface $directive): void;

    public function unregister(string $name, ?string $method = null): void;

    public function has(string $name, ?string $method = null): bool;

    public function get(string $name, ?string $method = null): DirectiveDefinitionInterface;

    public function isLoop(string $name, ?string $method = null): bool;
}
```

## 对现有实现的重构建议

当前代码中，指令主要依赖：

- `AddonConfigManager::getDirectives()`
- `AddonDirectivesManage`
- `AddonDirectives`
- `PTCompiler`

建议的重构方向：

1. 保留 `PTCompiler` 作为模板编译入口
2. 将 `AddonDirectivesManage` 改造成真正的指令注册中心
3. 去掉对插件配置文件 `directives` 字段的依赖
4. 由插件 `Bootstrap` 在启用时完成指令注册
5. `PTCompiler` 和 `AddonDirectivesActuator` 均改为查询注册中心

## 重构策略建议

当前仓库处于开发阶段时，建议直接采用纯代码注册方案：

1. 保留 `PTCompiler` 作为模板编译入口
2. 将 `AddonDirectivesManage` 改造成统一指令注册中心
3. 删除插件配置文件中的 `directives` 支持
4. 由插件 `Bootstrap` 在启用时完成指令注册
5. `PTCompiler` 和 `AddonDirectivesActuator` 全部查询注册中心

## 当前结论

模板指令与一般配置项不同，它更接近“编译期扩展协议”。

因此，指令的最佳落点不是 `json` 配置，而是代码中的统一注册规范。

这会比配置式方案更适合长期维护，也更适合你现在准备做的插件管理器整体升级。
