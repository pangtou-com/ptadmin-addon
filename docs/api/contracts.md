# 能力接口约定

插件管理器中的底层能力调用统一约定为：

```php
Addon::executeInject($group, $code, $payload, $action);
```

说明：

- `$group`：能力分组，如 `payment`
- `$code`：具体实现编码，如 `wechat_pay`
- `$payload`：固定输入字段集合
- `$action`：具体动作，如 `create`、`refund`、`send`

支付能力在业务层推荐优先使用：

```php
Addon::payment()
Addon::payments()
Addon::payment($addonCode, $code)->channel('jsapi')->create($payload)
```

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
| `scene` | `string` | 支付场景，固定取值如 `jsapi` `app` `h5` `native` `miniapp` |
| `order_no` | `string` | 业务订单号 |
| `amount` | `string\|int\|float` | 支付金额 |
| `subject` | `string` | 订单标题 |
| `notify_url` | `string` | 异步回调地址 |
| `return_url` | `string\|null` | 同步返回地址 |
| `open_id` | `string\|null` | 用户标识 |
| `client_ip` | `string\|null` | 客户端 IP |
| `currency` | `string\|null` | 币种 |
| `meta` | `array` | 渠道专属参数 |

固定输出：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `status` | `string` | 支付单状态 |
| `scene` | `string` | 当前支付场景 |
| `action` | `string` | 前端动作，固定取值如 `invoke` `redirect` `form` `qrcode` `none` |
| `channel_trade_no` | `string\|null` | 渠道交易号 |
| `payload` | `array` | 标准拉起参数 |
| `display` | `array` | 展示辅助数据，如二维码链接、表单片段 |
| `expires_at` | `string\|null` | 过期时间 |
| `meta` | `array` | 渠道扩展输出 |
| `raw` | `mixed` | 原始响应 |

### `query`

固定输入：`order_no` `channel_trade_no` `meta`

固定输出：`order_no` `channel_trade_no` `status` `paid_at` `amount` `meta` `raw`

### `refund`

固定输入：`order_no` `refund_no` `amount` `reason` `meta`

固定输出：`order_no` `refund_no` `channel_refund_no` `status` `refunded_at` `amount` `meta` `raw`

### `queryRefund`

固定输入：`refund_no` `channel_refund_no` `meta`

固定输出：`refund_no` `channel_refund_no` `status` `refunded_at` `amount` `meta` `raw`

### `parseNotify`

固定输入：`body` `headers` `query` `meta`

固定输出：`event` `order_no` `refund_no` `channel_trade_no` `channel_refund_no` `status` `amount` `paid_at` `meta` `raw`

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

```php
$manager->register(
    'payment-addon',
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->types(['jsapi', 'app', 'refund'])
        ->handler(WechatPayService::class)
);
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
