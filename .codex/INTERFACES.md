# 插件接口草案

## 目标

本文件定义插件管理器在一期建议约定的接口边界，主要覆盖：

- 安装周期接口
- 启用周期接口
- 安装上下文
- 启用上下文

目标不是直接产出最终 PHP 代码，而是先把管理器与插件之间的调用契约约束清楚。

## 设计原则

- 插件管理器负责调度
- 插件负责实现自身逻辑
- 安装期和运行期职责分离
- 数据库访问仅允许在安装期接口中出现

## 安装周期接口

安装周期建议通过 `AddonInstallerInterface` 约定。

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface AddonInstallerInterface
{
    public function install(InstallContextInterface $context): void;

    public function init(InstallContextInterface $context): void;

    public function upgrade(
        string $fromVersion,
        string $toVersion,
        InstallContextInterface $context
    ): void;

    public function uninstall(InstallContextInterface $context): void;
}
```

## 方法职责

### `install()`

职责：

- 执行安装动作
- 完成插件基础写入后的安装处理
- 可执行必要的数据表创建、配置初始化准备

不建议：

- 注册运行期能力
- 注册 hooks
- 注册指令

### `init()`

职责：

- 初始化默认数据
- 初始化权限、菜单、配置项
- 执行首次安装后的收尾逻辑

说明：

- 如果安装器希望将“结构安装”和“业务初始化”拆开，`install()` 与 `init()` 分离会更清晰

### `upgrade()`

职责：

- 执行版本升级逻辑
- 处理结构差异和迁移脚本
- 兼容旧版本数据

说明：

- 升级入口在一期可以先预留，后续实现

### `uninstall()`

职责：

- 卸载时执行清理逻辑
- 根据策略删除配置、菜单、权限、数据表或保留业务数据

说明：

- 是否允许删除业务数据，建议由管理器传入策略参数控制，而不是插件自行决定

## 运行周期接口

运行周期建议通过 `AddonBootstrapInterface` 约定。

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface AddonBootstrapInterface
{
    public function boot(BootstrapContextInterface $context): void;

    public function shutdown(BootstrapContextInterface $context): void;
}
```

## 方法职责

### `boot()`

职责：

- 注册能力实现
- 注册 inject 能力
- 注册 hooks 监听器
- 注册模板指令
- 注册路由、容器绑定、运行期扩展

不建议：

- 执行数据库结构变更
- 执行安装型初始化逻辑

### `shutdown()`

职责：

- 卸载运行期注册
- 注销监听器或清理内存态状态

说明：

- 如果某些运行时注册无法显式注销，也至少应保证停用后不会再被调度

## 安装上下文接口

安装器不应直接依赖整个插件管理器对象，建议提供收敛过的安装上下文。

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface InstallContextInterface
{
    public function addonId(): string;

    public function addonCode(): string;

    public function version(): string;

    public function addonPath(): string;

    public function tempPath(): ?string;

    public function manifest(): array;

    public function options(): array;
}
```

## 安装上下文字段建议

- `addonId()`：插件唯一 ID
- `addonCode()`：插件代码标识
- `version()`：当前安装版本
- `addonPath()`：正式安装目录
- `tempPath()`：临时解压目录，安装完成后可为空
- `manifest()`：插件静态清单
- `options()`：安装选项，如是否覆盖、是否静默、是否保留数据

## 运行上下文接口

`bootstrap` 需要使用平台提供的注册中心，但不应直接拿到过大的宿主对象。

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface BootstrapContextInterface
{
    public function addonId(): string;

    public function addonCode(): string;

    public function manifest(): array;

    public function capabilities(): CapabilityRegistryInterface;

    public function injects(): InjectRegistryInterface;

    public function hooks(): HookRegistryInterface;

    public function directives(): DirectiveRegistryInterface;

    public function container(): ContainerInterface;

    public function routes(): RouteRegistryInterface;
}
```

## 注册中心接口建议

### 能力注册中心

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface CapabilityRegistryInterface
{
    public function register(string $name, string $handler): void;

    public function unregister(string $name): void;
}
```

### Inject 注册中心

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface InjectRegistryInterface
{
    public function register(string $group, InjectDefinitionInterface $definition): void;
}
```

### Hooks 注册中心

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface HookRegistryInterface
{
    public function listen(string $event, string $listener): void;

    public function remove(string $event, string $listener): void;
}
```

### 指令注册中心

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface DirectiveRegistryInterface
{
    public function register(DirectiveDefinitionInterface $directive): void;

    public function unregister(string $name, ?string $method = null): void;
}
```

### 指令定义对象

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface DirectiveDefinitionInterface
{
    public function name(): string;

    public function handler(): string;

    public function method(): string;

    public function type(): string;

    public function cacheable(): bool;
}
```

说明：

- `name()`：模板指令名称，如 `lists`
- `handler()`：指令处理器类
- `method()`：调用方法，默认可约定为 `handle`
- `type()`：指令类型，建议一期支持 `loop`、`output`、`if`
- `cacheable()`：是否允许缓存

### Inject 定义对象

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface InjectDefinitionInterface
{
    public function code(): string;

    public function title(): ?string;

    public function types(): array;

    public function handler(): string;
}
```

## 通用能力接口建议

对于支付、第三方登录、通知、存储这类可复用能力，建议统一通过 `inject` 分组暴露，并实现稳定的接口契约。

推荐分组：

- `payment`
- `auth`
- `notify`
- `storage`
- `sms`
- `ai`
- `captcha`
- `logistics`

推荐接口示意：

```php
<?php

namespace PTAdmin\Addon\Contracts;

interface CapabilityInterface
{
    public function supports(string $operation): bool;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;

interface PaymentInterface extends CapabilityInterface
{
    public function create(CreatePaymentRequest $payload): CreatePaymentResult;

    public function query(QueryPaymentRequest $payload): QueryPaymentResult;

    public function refund(RefundPaymentRequest $payload): RefundPaymentResult;

    public function queryRefund(QueryRefundRequest $payload): QueryRefundResult;

    public function parseNotify(ParsePaymentNotifyRequest $payload): ParsePaymentNotifyResult;

    public function acknowledgeNotify(AcknowledgePaymentNotifyRequest $payload): AcknowledgePaymentNotifyResult;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Auth;

interface AuthInterface extends CapabilityInterface
{
    public function getAuthorizeUrl(InjectPayload $payload): array;

    public function handleCallback(InjectPayload $payload): array;

    public function getUser(InjectPayload $payload): array;

    public function refreshToken(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Notify;

interface NotifyInterface extends CapabilityInterface
{
    public function send(InjectPayload $payload): array;

    public function sendBatch(InjectPayload $payload): array;

    public function query(InjectPayload $payload): array;

    public function parseCallback(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Storage;

interface StorageInterface extends CapabilityInterface
{
    public function upload(InjectPayload $payload): array;

    public function delete(InjectPayload $payload): bool;

    public function exists(InjectPayload $payload): bool;

    public function temporaryUrl(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Sms;

interface SmsInterface extends CapabilityInterface
{
    public function send(InjectPayload $payload): array;

    public function sendBatch(InjectPayload $payload): array;

    public function query(InjectPayload $payload): array;

    public function parseReceipt(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\AI;

interface AIInterface extends CapabilityInterface
{
    public function chat(InjectPayload $payload): array;

    public function generate(InjectPayload $payload): array;

    public function embedding(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Captcha;

interface CaptchaInterface extends CapabilityInterface
{
    public function generate(InjectPayload $payload): array;

    public function verify(InjectPayload $payload): bool;

    public function refresh(InjectPayload $payload): array;
}
```

```php
<?php

namespace PTAdmin\Addon\Contracts\Logistics;

interface LogisticsInterface extends CapabilityInterface
{
    public function query(InjectPayload $payload): array;

    public function subscribe(InjectPayload $payload): array;

    public function parseCallback(InjectPayload $payload): array;
}
```

建议约束：

- 宿主侧只按 `group + code` 选择能力实现，不耦合具体插件类名
- 动作调用建议统一为 `group + code + action + payload`
- 插件实现应保证返回结构稳定，便于上层业务做统一适配
- 复杂能力优先使用请求 DTO 与结果 DTO，避免继续扩散裸数组
- 同一能力分组下可存在多个实现，例如多个支付渠道、多个短信供应商

## 模板指令注册规范

模板指令建议统一改为代码注册，不再通过 `json` 或 `manifest` 配置处理器映射。

推荐原因：

- 便于 IDE 跳转与重构
- 便于做静态检查与类型约束
- 便于编译器与执行器共享同一份指令定义

推荐示意：

```php
<?php

namespace Addon\Cms;

use PTAdmin\Addon\Contracts\AddonBootstrapInterface;
use PTAdmin\Addon\Contracts\BootstrapContextInterface;
use PTAdmin\Addon\Support\DirectiveDefinition;
use Addon\Cms\Directive\ListsDirective;

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

建议约束：

- 一个指令对应一个清晰的处理器类
- 默认方法名建议统一为 `handle`
- 指令类型必须显式声明，避免编译器猜测
- 处理器类应是可被容器解析的类
- 指令名应在插件内保持稳定，跨版本避免随意变更

## 管理器调度建议

插件管理器在不同阶段建议按以下方式调度：

### 安装

1. 读取并校验 `manifest`
2. 拷贝插件到 `addons/<id>`
3. 实例化 `entry.installer`
4. 调用 `install($context)`
5. 调用 `init($context)`
6. 标记安装成功

### 启用

1. 读取已安装插件信息
2. 实例化 `entry.bootstrap`
3. 调用 `boot($context)`
4. 标记插件已启用

### 停用

1. 实例化 `entry.bootstrap`
2. 调用 `shutdown($context)`
3. 标记插件已停用

### 卸载

1. 实例化 `entry.installer`
2. 调用 `uninstall($context)`
3. 删除安装目录或标记待删除
4. 标记已卸载

## 异常处理建议

建议插件接口统一通过抛异常报告失败，不建议返回布尔值。

原因：

- 布尔值信息不足
- 异常更利于安装器记录失败原因
- 便于做回滚和错误分类

建议至少区分：

- 校验异常
- 安装异常
- 初始化异常
- 升级异常
- 卸载异常

## 一期实现建议

为了降低复杂度，一期建议：

- 所有接口方法返回 `void`
- 失败统一抛异常
- `manifest()` 先返回数组，后续再演进成强类型对象
- `CapabilityRegistryInterface`、`HookRegistryInterface`、`DirectiveRegistryInterface` 先只保留最小注册能力
- 指令注册优先使用定义对象，不再使用纯数组配置

## 当前结论

一期最核心的契约如下：

- `manifest` 负责描述插件
- `AddonInstallerInterface` 负责安装周期
- `AddonBootstrapInterface` 负责运行周期
- 管理器通过上下文对象与注册中心将能力暴露给插件

这套接口边界已经足够支撑你当前描述的云端安装、本地上传安装、初始化、启停和能力注册场景。
