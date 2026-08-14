# 支付能力协议 v2

支付协议 v2 用于声明、发现和精确调用已安装插件提供的支付能力。协议只描述技术能力，不读取平台业务绑定，不根据 User-Agent 判断场景，也不会自动选择第一个支付插件。

## 调用流程

```text
插件声明支付能力
  -> 调用方按 target/scene/interaction/operation 查询
  -> 平台将候选能力绑定为自己的 method_code
  -> 用户或调用方选择 method_code
  -> 平台解析完整能力引用并调用 PaymentGateway
```

公网接口通常只暴露平台的 `method_code`。`addon_code`、`capability_code`、`profile_code`、`scene` 和 `protocol_version` 是平台服务内部保存和解析的精确引用。

## 协议常量

- `PaymentTarget`：`pc`、`mobile_web`、`wechat_webview`、`alipay_webview`、`mini_program`、`native_app`
- `PaymentScene`：`qr`、`web`、`h5`、`jsapi`、`mini_program`、`app`
- `PaymentInteraction`：`qr_code`、`redirect`、`form_submit`、`client_invoke`、`none`
- `PaymentOperation`：`prepare`、`create`、`query`、`close`、`refund`、`query_refund`、`parse_notify`、`acknowledge_notify`
- 支付状态：`pending`、`processing`、`succeeded`、`failed`、`closed`、`expired`
- 退款状态：`pending`、`processing`、`succeeded`、`failed`、`closed`

未知值会在声明或调用边界被拒绝。插件不能自行增加公共场景或状态。

## 插件声明

```php
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInput;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteraction;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentOperation;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentScene;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentSceneDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentTarget;
use PTAdmin\Addon\Service\InjectDefinition;

$qr = PaymentSceneDefinition::make(
    PaymentScene::QR,
    [PaymentTarget::PC],
    [PaymentInteraction::QR_CODE],
    [
        PaymentOperation::CREATE,
        PaymentOperation::QUERY,
        PaymentOperation::CLOSE,
        PaymentOperation::REFUND,
        PaymentOperation::QUERY_REFUND,
        PaymentOperation::PARSE_NOTIFY,
        PaymentOperation::ACKNOWLEDGE_NOTIFY,
    ],
    [PaymentInput::CURRENCY],
    ['CNY']
);

$manager->register(
    'payment-addon',
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->handler(WechatPayment::class)
        ->paymentDefinition(
            PaymentDefinition::make('wechat_pay', [$qr])->title('微信支付')
        )
);
```

`InjectDefinition` 与 `PaymentDefinition` 的能力编码必须一致。`client_invoke` 场景还必须使用 `PaymentExecutor` 声明执行器编码和版本。

## 按调用方目标发现

PC 页面不需要预先知道插件编码：

```php
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteraction;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentOperation;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentRequirements;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentScene;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentTarget;

$requirements = PaymentRequirements::make()
    ->target(PaymentTarget::PC)
    ->scenes([PaymentScene::QR, PaymentScene::WEB])
    ->interactions([
        PaymentInteraction::QR_CODE,
        PaymentInteraction::REDIRECT,
        PaymentInteraction::FORM_SUBMIT,
    ])
    ->operations([
        PaymentOperation::CREATE,
        PaymentOperation::QUERY,
        PaymentOperation::PARSE_NOTIFY,
        PaymentOperation::ACKNOWLEDGE_NOTIFY,
    ])
    ->currency('CNY');

$capabilities = addon_payments()->discover($requirements);
```

小程序调用方将 `target` 改为 `PaymentTarget::MINI_PROGRAM`，并明确查询 `PaymentScene::MINI_PROGRAM` 和 `PaymentInteraction::CLIENT_INVOKE`。支付注册必须提供 v2 声明，缺失声明时注册直接失败。

技术目录返回完整引用和声明信息，不生成平台 `method_code`：

```php
[
    'addon_code' => 'payment-addon',
    'capability_code' => 'wechat_pay',
    'profile_code' => 'default',
    'scene' => 'qr',
    'protocol_version' => 2,
    'title' => '微信支付',
    'interactions' => ['qr_code'],
    'operations' => ['create', 'query', 'close', 'refund', 'query_refund', 'parse_notify', 'acknowledge_notify'],
    'required_inputs' => ['currency'],
    'currencies' => ['CNY'],
    'executors' => [],
]
```

平台业务层负责把该引用绑定到自己的 `method_code`，并应用业务启用状态、排序和用户权限。

## 就绪与精确调用

插件可以实现 `PaymentReadinessInterface`，按完整引用返回 `PaymentReadinessResult`。检查只验证插件启用状态和必要配置，不发起远程支付请求。

```php
$reference = PaymentCapabilityReference::fromArray($storedReference);

$readiness = addon_payments()->readiness($reference);
if (!$readiness->isReady()) {
    // 使用 reason_code 和 message 显示或记录不可用原因
}

$gateway = addon_payments()->gateway($reference);
$result = $gateway->create([
    'order_no' => 'T1001',
    'amount_minor' => 9900,
    'currency' => 'CNY',
    'subject' => '订单支付',
    'notify_url' => 'https://example.com/pay/notify',
]);
```

`prepare` 用于显式准备 `payer_reference`，不会自动触发 OAuth 或客户端桥接。`query`、`close`、`refund`、`queryRefund`、`parseNotify` 和 `acknowledgeNotify` 必须继续使用创建支付时保存的同一引用。

网关在每个请求的 `meta.payment_context` 中传递：

```text
addon_code + capability_code + profile_code + scene + protocol_version
```

## 创建结果

v2 插件必须返回标准结果：

```php
CreatePaymentResult::fromArray([
    'protocol_version' => 2,
    'status' => 'pending',
    'scene' => PaymentScene::QR,
    'interaction' => [
        'type' => PaymentInteraction::QR_CODE,
        'payload' => ['content' => 'weixin://wxpay/...'],
    ],
    'channel_trade_no' => null,
    'expires_at' => null,
    'meta' => [],
]);
```

网关会验证状态、场景、已声明交互和交互载荷。二维码要求 `content`；跳转要求合法 `url`；表单要求 `url`、`GET|POST` 和标量 `fields`；客户端调用要求 `executor`、`version` 和 `parameters`。插件不得返回 HTML、Blade 或任意 JavaScript。

## 唯一支付入口

支付协议不保留 `Addon::payment()`、`Addon::payments()`、`addon_payment()`、`channel()` 或支付分组的通用 `executeInject()` 调用。支付注册也不使用 `types()` 表达场景。所有支付能力统一通过 `PaymentDefinition` 注册、`addon_payments()` 发现，并通过完整 `PaymentCapabilityReference` 调用。

这是破坏性协议升级。宿主升级依赖前必须完成支付插件、支付方式绑定和历史记录场景的迁移。

统一二维码是平台收银台会话编排，不是 `PaymentScene`，也不由支付插件声明。上游协议只负责平台选定具体能力后的精确调用。
