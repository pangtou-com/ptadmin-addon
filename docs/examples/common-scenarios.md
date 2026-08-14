# 常见场景

## 支付能力插件

支付插件必须通过 `PaymentDefinition` 声明能力。业务侧先按运行目标发现：

```php
$requirements = PaymentRequirements::make()
    ->target(PaymentTarget::PC)
    ->scenes([PaymentScene::QR, PaymentScene::WEB]);

$methods = addon_payments()->discover($requirements);
```

平台将用户选择的方式解析为完整引用后调用：

```php
$reference = PaymentCapabilityReference::fromArray($storedMethodReference);
$result = addon_payments()->gateway($reference)->create([
    'order_no' => 'T1001',
    'amount_minor' => 9900,
    'currency' => 'CNY',
    'subject' => '订单支付',
    'notify_url' => 'https://example.com/pay/notify',
]);
```

查询、关单、退款和通知必须继续使用同一引用，不能重新发现或默认选择。完整示例见[支付能力协议 v2](/api/payment-protocol-v2.md)。

## 第三方登录插件

```php
$manager->register(
    'auth-addon',
    'auth',
    InjectDefinition::make('qq_login')
        ->title('QQ 登录')
        ->types(['pc', 'mobile'])
        ->handler(QQLoginService::class)
);
```

## 消息通知插件

```php
$manager->register(
    'notify-addon',
    'notify',
    InjectDefinition::make('site_notify')
        ->title('站内通知')
        ->types(['site', 'template'])
        ->handler(SiteNotifyService::class)
);
```

## OSS 存储插件

```php
$manager->register(
    'storage-addon',
    'storage',
    InjectDefinition::make('oss_storage')
        ->title('OSS 存储')
        ->types(['oss', 'private'])
        ->handler(OssStorageService::class)
);
```

## 短信发送插件

```php
$manager->register(
    'sms-addon',
    'sms',
    InjectDefinition::make('aliyun_sms')
        ->title('阿里云短信')
        ->types(['verify', 'notice'])
        ->handler(AliyunSmsService::class)
);
```

## AI 能力插件

```php
$manager->register(
    'ai-addon',
    'ai',
    InjectDefinition::make('openai_chat')
        ->title('OpenAI 对话')
        ->types(['chat', 'completion'])
        ->handler(OpenAIChatService::class)
);
```

## 图形验证码插件

```php
$manager->register(
    'captcha-addon',
    'captcha',
    InjectDefinition::make('image_captcha')
        ->title('图形验证码')
        ->types(['image'])
        ->handler(ImageCaptchaService::class)
);
```

## 物流查询插件

```php
$manager->register(
    'logistics-addon',
    'logistics',
    InjectDefinition::make('kdniao')
        ->title('快递鸟物流')
        ->types(['track'])
        ->handler(KdniaoLogisticsService::class)
);
```

## Hook 监听支付成功

```php
$manager->register(
    'order-addon',
    HookDefinition::make('payment.success')
        ->handler(OrderPaidListener::class.'@handle')
        ->priority(10)
);
```

触发方：

```php
Addon::triggerHook('payment.success', [
    'order_id' => 1001,
]);
```
