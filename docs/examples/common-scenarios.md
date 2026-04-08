# 常见场景

## 支付能力插件

适合通过 `inject` 暴露能力：

```php
$manager->register(
    'payment-addon',
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->types(['jsapi', 'qrcode'])
        ->handler(WechatPayService::class)
);
```

业务侧调用：

```php
$result = Addon::payment('payment-addon', 'wechat_pay')
    ->channel('jsapi')
    ->create([
        'order_no' => 'T1001',
        'amount' => 99.9,
        'subject' => '订单支付',
        'notify_url' => 'https://example.com/pay/notify',
    ]);
```

如果需要展示所有可用支付插件：

```php
$payments = Addon::payments();
```

如果需要直接切指定支付插件：

```php
$wechat = Addon::payment('payment-addon', 'wechat_pay');
```

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
