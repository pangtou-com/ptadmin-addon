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
$injects = Addon::getInjects('auth');
$hooks = Addon::getHooks();
```

## 公开能力目录

使用 `Addon::capabilities()` 或 `addon_cap()` 获取经过清理的公开能力描述。目录不会返回处理类等内部实现字段。

```php
$registered = Addon::capabilities('auth')->all();
$available = addon_cap('auth')->available();

$supportsWechat = addon_cap('auth')->has('wechat');
$wechatReady = addon_cap('auth')->isAvailable('wechat', 'wechat_login');
```

`all()` 表示插件已经注册该能力；`available()` 会额外调用能力可选的 `ready` 动作。未声明 `ready` 的已有插件仍按可用处理。宿主应用仍需继续检查业务场景、平台开关、订单和终端类型，不能把能力目录直接当作结算方式列表。

公开定义使用稳定结构：

```php
[
    'addon_code' => 'wechat_login',
    'group' => 'auth',
    'code' => 'wechat',
    'title' => '微信',
    'types' => ['login', 'connect'],
]
```

## 第三方认证能力

第三方认证能力推荐使用 `Addon::auth()` 或 `addon_auth()` 调用：

```php
$auth = addon_auth('wechat');

if ($auth->ready()) {
    $authorization = $auth->getAuthorizeUrl([
        'redirect_url' => 'https://example.com/auth/callback',
        'state' => $state,
        'scope' => 'snsapi_login',
        'scene' => 'website',
        'meta' => [],
    ]);
}
```

## 支付能力

支付能力不提供默认插件或按编码直接调用的 Facade。调用方必须先按明确目标发现，再使用完整引用调用：

```php
$methods = Addon::paymentCatalog()->discover($requirements);
$reference = PaymentCapabilityReference::fromArray($storedMethodReference);
$gateway = Addon::paymentCatalog()->gateway($reference);
$result = $gateway->create($payload);
```

完整声明和结果结构见[支付能力协议 v2](/api/payment-protocol-v2.md)。

## 调用 inject

`executeInject()` 仍然保留，但只适合非支付能力。`payment` 分组会被拒绝。

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
