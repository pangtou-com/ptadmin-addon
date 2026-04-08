# Inject 规范

## 目标

`inject` 用于表达“插件向平台或其他插件提供一类可被选择的能力实现”。

适用场景：

- 支付实现
- 登录实现
- 短信实现
- 消息通知实现
- 存储实现

推荐分组：

- `payment`：支付能力，如微信支付、支付宝
- `auth`：第三方登录能力，如 QQ、微信、手机号登录
- `sms`：短信发送能力，如阿里云短信、腾讯云短信
- `ai`：AI 能力，如对话、生成、向量检索
- `captcha`：图形验证码能力，如图片验证码、滑块验证码
- `notify`：消息通知能力，如站内通知、邮件、Webhook、企业微信通知
- `storage`：文件存储能力，如本地、OSS、COS、S3
- `logistics`：物流能力，如物流轨迹查询、快递状态同步

## 设计定位

`inject` 不是事件系统，也不是模板指令。

它的职责是：

- 声明某插件提供了某类能力实现
- 让宿主系统按分组查找可用实现
- 让业务代码按 `code` 选择并调用具体实现

## 推荐做法

- 通过代码注册，不通过 `json` 配置处理器映射
- 按能力分组注册，如 `payment`、`auth`、`sms`、`storage`
- 每个实现都应有稳定 `code`

## 最小定义

一个 inject 定义至少应包含：

- `code`
- `title`
- `type`
- `handler`

说明：

- `handler` 推荐直接指向能力类，而不是某个固定方法
- 具体执行动作建议通过 `action` 指定，如 `create`、`refund`、`send`
- 一个能力类可暴露多个动作方法，并通过 `supports()` 声明支持范围

## 代码示意

```php
$manager->register(
    'demo-addon',
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->types(['jsapi', 'qrcode'])
        ->handler(Addon\Payment\WechatPay::class)
);
```

```php
Addon::payment('payment-addon', 'wechat_pay')
    ->channel('jsapi')
    ->create([
        'order_no' => 'T1001',
        'amount' => 99.9,
        'subject' => '订单支付',
        'notify_url' => 'https://example.com/pay/notify',
    ]);
```

## 当前实现

当前仓库中：

- 注册中心：`AddonInjectsManage`
- 定义对象：`InjectDefinition`
- 插件注册入口：`BaseBootstrap::registerInjects()`
- 调用入口：`Addon::executeInject()`

## 使用建议

- 主流程需要“找一个具体实现来执行”时使用 `inject`
- 不要用 `inject` 做广播通知
- 不要把实现类映射放回配置文件
- 如果能力需要统一调用协议，优先约定 `group + code + action + payload`
