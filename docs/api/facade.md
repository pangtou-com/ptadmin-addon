# Facade API

常用 Facade 为 `PTAdmin\Addon\Addon`。

## 查询插件

```php
use PTAdmin\Addon\Addon;

$exists = Addon::hasAddon('demo-addon');
$version = Addon::getAddonVersion('demo-addon');
$path = Addon::getAddonPath('demo-addon');
```

## 获取运行期注册信息

```php
$directives = Addon::getDirectives();
$injects = Addon::getInjects('payment');
$hooks = Addon::getHooks();
```

## 支付能力

支付能力推荐直接通过 `Addon::payment()` 调用，而不是手动拼 `group + code + action`。

```php
$payment = Addon::payment();
$payments = Addon::payments();
$wechat = Addon::payment('payment-addon', 'wechat_pay');
```

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

```php
$refund = Addon::payment('payment-addon', 'wechat_pay')->refund([
    'order_no' => 'T1001',
    'refund_no' => 'R1001',
    'amount' => 20,
]);
```

## 调用 inject

`executeInject()` 仍然保留，适合底层能力分发或非支付类能力场景。

```php
$result = Addon::executeInject('notify', 'site_notify', [
    'channel' => 'site',
    'message' => 'hello',
], 'send');
```

## 触发 hook

```php
$results = Addon::triggerHook('payment.success', [
    'order_id' => 1001,
]);
```

## 指令执行

```php
$result = Addon::execute('demo-addon', 'lists', [
    'limit' => 10,
]);
```
