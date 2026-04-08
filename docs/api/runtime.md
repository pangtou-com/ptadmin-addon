# 运行期注册 API

## BaseInstaller

```php
use PTAdmin\Addon\Service\BaseInstaller;

class Installer extends BaseInstaller
{
    public function beforeInstall(): bool
    {
        return true;
    }

    public function install(): void
    {
    }

    public function init(): void
    {
    }

    public function upgrade(?string $fromVersion = null, ?string $toVersion = null): void
    {
    }

    public function uninstall(): void
    {
    }
}
```

## BaseBootstrap

```php
use PTAdmin\Addon\Service\BaseBootstrap;

class Bootstrap extends BaseBootstrap
{
    public function enable(): void
    {
    }

    public function disable(): void
    {
    }
}
```

## 指令注册

```php
use PTAdmin\Addon\Service\DirectiveDefinition;

$manager->register(
    'demo-addon',
    DirectiveDefinition::make('lists')
        ->title('列表')
        ->handler(ListsDirective::class)
        ->method('handle')
        ->type('loop')
        ->cacheable(true)
);
```

## Inject 注册

```php
use PTAdmin\Addon\Service\InjectDefinition;

$manager->register(
    'demo-addon',
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->types(['jsapi', 'qrcode'])
        ->handler(WechatPayService::class)
);
```

推荐做法：

- `handler()` 直接指向能力类，而不是固定某个方法
- 支付能力优先通过 `Addon::payment()`、`Addon::payments()` 暴露统一入口
- 其他能力可继续通过 `Addon::executeInject(..., 'action')` 指定动作
- 能力类应实现对应 `Contracts`，并通过 `supports()` 说明支持哪些动作
- 复杂能力建议直接使用请求对象和响应对象约束输入输出

如果某个 inject 分组已经有正式接口约定，建议实现对应 `Contracts`：

- `payment` -> `PaymentInterface`
- `auth` -> `AuthInterface`
- `notify` -> `NotifyInterface`
- `storage` -> `StorageInterface`
- `sms` -> `SmsInterface`
- `ai` -> `AIInterface`
- `captcha` -> `CaptchaInterface`
- `logistics` -> `LogisticsInterface`

## Hook 注册

```php
use PTAdmin\Addon\Service\HookDefinition;

$manager->register(
    'demo-addon',
    HookDefinition::make('payment.success')
        ->handler(PaymentSuccessListener::class.'@handle')
        ->priority(10)
);
```
