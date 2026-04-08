# 插件样板

这份样板用于说明当前插件体系下，一个业务插件最小应该如何组织。

当前插件管理器只负责通用能力：

- 插件安装、卸载、升级、启停
- 资源发现
- `directives` 注册
- `inject` 注册
- `hooks` 注册

业务功能不要继续堆到插件管理器中，应留在插件自身实现。

## 推荐目录

```text
addons/Demo
├── Bootstrap.php
├── Installer.php
├── manifest.json
├── Directive
│   └── ListsDirective.php
├── Inject
│   ├── DemoPayService.php
│   ├── DemoLoginService.php
│   ├── DemoSmsService.php
│   ├── DemoAiService.php
│   ├── DemoCaptchaService.php
│   ├── DemoNotifyService.php
│   ├── DemoStorageService.php
│   └── DemoLogisticsService.php
├── Listeners
│   └── PaymentSuccessListener.php
├── Routes
├── Response
│   ├── Views
│   └── Lang
├── Config
└── functions.php
```

## 配置文件

`manifest.json` 现在只保留静态信息、入口声明和资源声明，不再放 `directives`、`inject`、`hooks` 的实现映射。

```json
{
  "id": "demo",
  "title": "Demo 插件",
  "code": "demo",
  "version": "1.0.0",
  "develop": false,
  "description": "演示插件",
  "authors": {
    "name": "PTAdmin",
    "email": "vip@pangtou.com"
  },
  "compatibility": {
    "ptadmin/admin": ">=1.0",
    "ptadmin/base": ">=1.0"
  },
  "entry": {
    "installer": "Addon\\Demo\\Installer",
    "bootstrap": "Addon\\Demo\\Bootstrap"
  },
  "resources": {
    "routes": "./Routes",
    "views": "./Response/Views",
    "lang": "./Response/Lang",
    "config": "./Config",
    "functions": "./functions.php"
  }
}
```

说明：

- `develop: false` 表示默认不是开发模式
- 本地正在开发的插件可改为 `true`
- 当插件处于开发模式时，升级流程默认不允许直接覆盖，必须显式使用强制升级

## Installer 入口

安装、初始化、升级、卸载统一放在 `Installer.php` 中。

```php
<?php

declare(strict_types=1);

namespace Addon\Demo;

use PTAdmin\Addon\Service\BaseInstaller;

class Installer extends BaseInstaller
{
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

## Bootstrap 入口

插件启用后的运行期扩展能力统一在 `Bootstrap.php` 中注册。

```php
<?php

declare(strict_types=1);

namespace Addon\Demo;

use Addon\Demo\Directive\ListsDirective;
use Addon\Demo\Inject\DemoLoginService;
use Addon\Demo\Inject\DemoNotifyService;
use Addon\Demo\Inject\DemoPayService;
use Addon\Demo\Inject\DemoSmsService;
use Addon\Demo\Inject\DemoAiService;
use Addon\Demo\Inject\DemoCaptchaService;
use Addon\Demo\Inject\DemoStorageService;
use Addon\Demo\Inject\DemoLogisticsService;
use Addon\Demo\Listeners\PaymentSuccessListener;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\BaseBootstrap;
use PTAdmin\Addon\Service\DirectiveDefinition;
use PTAdmin\Addon\Service\HookDefinition;
use PTAdmin\Addon\Service\InjectDefinition;

class Bootstrap extends BaseBootstrap
{
    public function registerDirectives(AddonDirectivesManage $manager): void
    {
        $manager->register(
            'demo',
            DirectiveDefinition::make('lists')
                ->title('Demo 列表')
                ->handler(ListsDirective::class)
                ->method('handle')
                ->type('loop')
                ->cacheable(true)
        );
    }

    public function registerInjects(AddonInjectsManage $manager): void
    {
        $manager->register(
            'demo',
            'payment',
            InjectDefinition::make('demo_pay')
                ->title('Demo 支付')
                ->types(['jsapi', 'qrcode'])
                ->handler(DemoPayService::class)
        );

        $manager->register(
            'demo',
            'auth',
            InjectDefinition::make('demo_login')
                ->title('Demo 登录')
                ->types(['pc', 'mobile'])
                ->handler(DemoLoginService::class)
        );

        $manager->register(
            'demo',
            'notify',
            InjectDefinition::make('demo_notify')
                ->title('Demo 通知')
                ->types(['site', 'mail'])
                ->handler(DemoNotifyService::class)
        );

        $manager->register(
            'demo',
            'storage',
            InjectDefinition::make('demo_storage')
                ->title('Demo 存储')
                ->types(['oss', 'private'])
                ->handler(DemoStorageService::class)
        );

        $manager->register(
            'demo',
            'sms',
            InjectDefinition::make('demo_sms')
                ->title('Demo 短信')
                ->types(['verify', 'notice'])
                ->handler(DemoSmsService::class)
        );

        $manager->register(
            'demo',
            'ai',
            InjectDefinition::make('demo_ai')
                ->title('Demo AI')
                ->types(['chat', 'completion'])
                ->handler(DemoAiService::class)
        );

        $manager->register(
            'demo',
            'captcha',
            InjectDefinition::make('demo_captcha')
                ->title('Demo 验证码')
                ->types(['image'])
                ->handler(DemoCaptchaService::class)
        );

        $manager->register(
            'demo',
            'logistics',
            InjectDefinition::make('demo_logistics')
                ->title('Demo 物流')
                ->types(['track'])
                ->handler(DemoLogisticsService::class)
        );
    }

    public function registerHooks(AddonHooksManage $manager): void
    {
        $manager->register(
            'demo',
            HookDefinition::make('payment.success')
                ->handler(PaymentSuccessListener::class.'@handle')
                ->priority(10)
        );
    }
}
```

## 本地安装

本地 zip 包可直接通过命令安装：

```bash
php artisan addon:install-local /path/to/demo.zip
```

补充说明：

- 安装器会先解压并读取 `manifest.json`
- 如果 `manifest.marketplace.checksum` 存在，会先做完整性校验
- 如果插件未声明 `marketplace` 信息，系统只提示并继续按本地插件安装
- 已安装插件需要覆盖时，显式传入 `--force`

## 指令示例

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Directive;

use PTAdmin\Addon\Service\DirectivesDTO;

class ListsDirective
{
    public function handle(DirectivesDTO $dto): array
    {
        return [
            ['title' => 'article-1'],
            ['title' => 'article-2'],
        ];
    }
}
```

模板中调用：

```blade
@pt:demo::lists(limit=10)
    {{ $item['title'] }}
@pt:end
```

## Inject 示例

### 支付

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;

class DemoPayService
{
    public function create(CreatePaymentRequest $payload): CreatePaymentResult
    {
        return CreatePaymentResult::fromArray([
            'status' => 'created',
            'scene' => $payload->get('scene'),
            'action' => 'invoke',
            'payload' => [
                'prepay_id' => 'demo-prepay-id',
            ],
        ]);
    }

    public function refund(RefundPaymentRequest $payload): RefundPaymentResult
    {
        return RefundPaymentResult::fromArray([
            'refund_no' => $payload->get('refund_no'),
            'amount' => $payload->get('amount'),
            'status' => 'success',
        ]);
    }
}
```

调用方式：

```php
$result = Addon::payment('demo', 'demo_pay')
    ->channel('jsapi')
    ->create([
        'order_no' => 'T1001',
        'amount' => 99.9,
        'subject' => '演示订单',
        'notify_url' => 'https://example.com/pay/notify',
    ]);
```

### 第三方登录

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoLoginService
{
    public function getAuthorizeUrl(InjectPayload $payload): array
    {
        return [
            'scene' => $payload->get('scene', 'pc'),
            'url' => 'https://example.test/oauth',
        ];
    }
}
```

### 消息通知

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoNotifyService
{
    public function send(InjectPayload $payload): array
    {
        return [
            'channel' => $payload->get('channel', 'site'),
            'message' => $payload->get('message'),
        ];
    }
}
```

### OSS 存储

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoStorageService
{
    public function upload(InjectPayload $payload): array
    {
        return [
            'disk' => $payload->get('disk', 'oss'),
            'path' => $payload->get('path'),
        ];
    }
}
```

### 短信发送

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoSmsService
{
    public function send(InjectPayload $payload): array
    {
        return [
            'mobile' => $payload->get('mobile'),
            'template' => $payload->get('template'),
            'success' => true,
        ];
    }
}
```

### AI 能力

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoAiService
{
    public function chat(InjectPayload $payload): array
    {
        return [
            'model' => $payload->get('model', 'demo-chat'),
            'content' => 'hello from demo ai',
        ];
    }
}
```

### 图形验证码

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoCaptchaService
{
    public function generate(InjectPayload $payload): array
    {
        return [
            'key' => 'captcha-demo-key',
            'image' => 'base64-image-content',
            'expires_at' => time() + 300,
        ];
    }

    public function verify(InjectPayload $payload): bool
    {
        return $payload->get('code') === '1234';
    }
}
```

### 物流查询

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Inject;

use PTAdmin\Addon\Service\InjectPayload;

class DemoLogisticsService
{
    public function query(InjectPayload $payload): array
    {
        return [
            'shipper_code' => $payload->get('shipper_code'),
            'tracking_no' => $payload->get('tracking_no'),
            'status' => 'in_transit',
        ];
    }
}
```

## Hook 示例

```php
<?php

declare(strict_types=1);

namespace Addon\Demo\Listeners;

use PTAdmin\Addon\Service\HookPayload;

class PaymentSuccessListener
{
    public function handle(HookPayload $payload): array
    {
        return [
            'event' => 'payment.success',
            'order_id' => $payload->get('order_id'),
        ];
    }
}
```

触发方式：

```php
use PTAdmin\Addon\Addon;

$results = Addon::triggerHook('payment.success', [
    'order_id' => 1001,
]);
```

## 推荐分工

- `directives`：模板扩展能力
- `inject`：可选择的能力提供者，建议统一按 `group + code + action + payload` 调用
- `hooks`：事件订阅扩展
- 业务逻辑：留在插件自身模块中

不要把业务规则重新堆回插件管理器。
