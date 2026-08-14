# 能力接口约定

非支付能力可以通过底层能力调用：

```php
Addon::executeInject($group, $code, $payload, $action);
```

说明：

- `$group`：能力分组，如 `auth`
- `$code`：具体实现编码，如 `wechat_pay`
- `$payload`：固定输入字段集合
- `$action`：具体动作，如 `getAuthorizeUrl`、`send`

支付能力只能通过支付技术目录发现，并使用完整能力引用调用：

```php
$methods = addon_payments()->discover($requirements);
$gateway = addon_payments()->gateway($reference);
$result = $gateway->create($payload);
```

能力发现和第三方认证可以使用公共 helper：

```php
addon_cap('auth')->all();
addon_cap('auth')->available();
addon_auth($code, $addonCode)->getAuthorizeUrl($payload);
```

`addon_cap()` 只用于非支付能力。支付不允许通过通用目录或 `executeInject()` 调用，完整流程见[支付能力协议 v2](/api/payment-protocol-v2.md)。

## 总体原则

- 同一能力分组下，输入字段名固定
- 同一能力分组下，输出字段名固定
- 复杂能力优先使用请求类和响应类约束输入输出
- 渠道专属输入统一放 `meta`
- 渠道专属输出统一放 `meta`
- 渠道原始响应统一放 `raw`
- 不使用的固定字段返回 `null`、`[]` 或 `false`

## 分组与接口

| inject 分组 | 接口 | 动作 |
| --- | --- | --- |
| `payment` | `PTAdmin\Addon\Contracts\Payment\PaymentInterface` | `create` `query` `refund` `queryRefund` `parseNotify` `acknowledgeNotify` |
| `payment`（可选准备能力） | `PTAdmin\Addon\Contracts\Payment\PreparablePaymentInterface` | 在基础支付动作上增加 `prepare` |
| `payment`（可选关闭能力） | `PTAdmin\Addon\Contracts\Payment\ClosablePaymentInterface` | 在基础支付动作上增加 `close` |
| `auth` | `PTAdmin\Addon\Contracts\Auth\AuthInterface` | `getAuthorizeUrl` `handleCallback` `getUser` `refreshToken` |
| `notify` | `PTAdmin\Addon\Contracts\Notify\NotifyInterface` | `send` `sendBatch` `query` `parseCallback` |
| `storage` | `PTAdmin\Addon\Contracts\Storage\StorageInterface` | `upload` `delete` `exists` `temporaryUrl` |
| `sms` | `PTAdmin\Addon\Contracts\Sms\SmsInterface` | `send` `sendBatch` `query` `parseReceipt` |
| `ai` | `PTAdmin\Addon\Contracts\AI\AIInterface` | `chat` `generate` `embedding` |
| `captcha` | `PTAdmin\Addon\Contracts\Captcha\CaptchaInterface` | `generate` `verify` `refresh` |
| `logistics` | `PTAdmin\Addon\Contracts\Logistics\LogisticsInterface` | `query` `subscribe` `parseCallback` |

## 支付

### `create`

固定输入：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `order_no` | `string` | 业务订单号 |
| `amount_minor` | `int` | 最小货币单位整数 |
| `subject` | `string` | 订单标题 |
| `notify_url` | `string` | 异步回调地址 |
| `return_url` | `string\|null` | 同步返回地址 |
| `payer_reference` | `string\|null` | 短期付款人引用 |
| `client_ip` | `string\|null` | 客户端 IP |
| `currency` | `string\|null` | 币种 |
| `meta` | `array` | 渠道专属参数 |

固定输出：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `protocol_version` | `int` | 固定为 `2` |
| `status` | `string` | 支付单状态 |
| `scene` | `string` | 当前支付场景 |
| `interaction` | `array` | 受约束的标准交互类型和载荷 |
| `channel_trade_no` | `string\|null` | 渠道交易号 |
| `expires_at` | `string\|null` | 过期时间 |
| `meta` | `array` | 渠道扩展输出 |
| `raw` | `mixed` | 原始响应 |

### `query`

固定输入：`order_no` `channel_trade_no` `meta`

固定输出：`order_no` `channel_trade_no` `status` `paid_at` `amount_minor` `currency` `meta` `raw`

### `close`

关闭支付单是可选能力。声明 `close` 的插件必须实现 `ClosablePaymentInterface`。

固定输入：`order_no` `channel_trade_no` `meta`

固定输出：`order_no` `channel_trade_no` `status` `closed_at` `meta` `raw`

### `refund`

固定输入：`order_no` `refund_no` `amount_minor` `currency` `reason` `meta`

固定输出：`order_no` `refund_no` `channel_refund_no` `status` `refunded_at` `amount_minor` `currency` `meta` `raw`

### `queryRefund`

固定输入：`refund_no` `channel_refund_no` `meta`

固定输出：`refund_no` `channel_refund_no` `status` `refunded_at` `amount_minor` `currency` `meta` `raw`

### `parseNotify`

固定输入：`body` `headers` `query` `meta`

固定输出：`event` `order_no` `refund_no` `channel_trade_no` `channel_refund_no` `status` `amount_minor` `currency` `paid_at` `meta` `raw`

### `acknowledgeNotify`

固定输入：`success` `message` `meta`

固定输出：`status_code` `headers` `body` `meta` `raw`

## 第三方登录

### `getAuthorizeUrl`

固定输入：`redirect_url` `state` `scope` `scene` `meta`

固定输出：`url` `state` `expires_at` `meta` `raw`

### `handleCallback`

固定输入：`code` `state` `meta`

固定输出：`openid` `unionid` `access_token` `refresh_token` `expires_at` `meta` `raw`

### `getUser`

固定输入：`access_token` `openid` `meta`

固定输出：`openid` `unionid` `nickname` `avatar` `email` `mobile` `meta` `raw`

### `refreshToken`

固定输入：`refresh_token` `meta`

固定输出：`access_token` `refresh_token` `expires_at` `meta` `raw`

## 消息通知

### `send`

固定输入：`channel` `receiver` `template` `subject` `message` `data` `meta`

固定输出：`message_id` `batch_id` `status` `accepted_at` `delivered_at` `meta` `raw`

### `sendBatch`

固定输入：`channel` `receivers` `template` `subject` `message` `data` `meta`

固定输出：`message_id` `batch_id` `status` `accepted_at` `delivered_at` `meta` `raw`

### `query`

固定输入：`message_id` `batch_id` `meta`

固定输出：`message_id` `batch_id` `status` `accepted_at` `delivered_at` `meta` `raw`

### `parseCallback`

固定输入：`body` `headers` `query` `meta`

固定输出：`message_id` `batch_id` `receiver` `status` `delivered_at` `meta` `raw`

## 文件存储

### `upload`

固定输入：`disk` `bucket` `path` `content` `stream` `visibility` `meta`

固定输出：`disk` `bucket` `path` `url` `size` `mime_type` `etag` `meta` `raw`

### `delete`

固定输入：`disk` `bucket` `path` `meta`

固定输出：`bool`

### `exists`

固定输入：`disk` `bucket` `path` `meta`

固定输出：`bool`

### `temporaryUrl`

固定输入：`disk` `bucket` `path` `expires_in` `disposition` `meta`

固定输出：`url` `expires_at` `meta` `raw`

## 短信

### `send`

固定输入：`mobile` `template` `sign` `data` `scene` `meta`

固定输出：`biz_id` `message_id` `status` `sent_at` `meta` `raw`

### `sendBatch`

固定输入：`mobiles` `template` `sign` `data` `scene` `meta`

固定输出：`biz_id` `batch_id` `status` `success_count` `fail_count` `meta` `raw`

### `query`

固定输入：`biz_id` `message_id` `mobile` `meta`

固定输出：`biz_id` `message_id` `mobile` `status` `delivered_at` `meta` `raw`

### `parseReceipt`

固定输入：`body` `headers` `query` `meta`

固定输出：`biz_id` `message_id` `mobile` `status` `delivered_at` `meta` `raw`

## AI

### `chat`

固定输入：`model` `messages` `stream` `temperature` `meta`

固定输出：`id` `model` `content` `items` `usage` `finish_reason` `meta` `raw`

### `generate`

固定输入：`model` `prompt` `format` `options` `meta`

固定输出：`id` `model` `content` `items` `usage` `finish_reason` `meta` `raw`

### `embedding`

固定输入：`model` `input` `meta`

固定输出：`id` `model` `vectors` `usage` `meta` `raw`

## 图形验证码

### `generate`

固定输入：`scene` `type` `width` `height` `meta`

固定输出：`key` `type` `content` `expires_at` `meta` `raw`

### `verify`

固定输入：`key` `code` `token` `meta`

固定输出：`bool`

### `refresh`

固定输入：`key` `scene` `type` `meta`

固定输出：`key` `type` `content` `expires_at` `meta` `raw`

## 物流

### `query`

固定输入：`shipper_code` `tracking_no` `mobile` `meta`

固定输出：`shipper_code` `tracking_no` `status` `signed` `current` `traces` `meta` `raw`

### `subscribe`

固定输入：`shipper_code` `tracking_no` `callback_url` `meta`

固定输出：`subscribe_id` `status` `meta` `raw`

### `parseCallback`

固定输入：`body` `headers` `query` `meta`

固定输出：`shipper_code` `tracking_no` `status` `signed` `current` `traces` `meta` `raw`

## 注册与调用示例

支付注册、发现、就绪检查和精确调用统一见[支付能力协议 v2](/api/payment-protocol-v2.md)。支付注册缺少 `PaymentDefinition` 会失败，不存在旧 `types + channel` 调用路径。
