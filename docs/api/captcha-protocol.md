# 反自动化挑战协议 v1

`captcha` 是插件能力分组。每个提供者必须同时声明后端能力和前端渲染器，宿主在创建挑战时固定 `addon_code`、能力 `code` 和渲染器引用；后续验证不会重新选择提供者。

## 首版类型

| 类型 | 响应示例 |
| --- | --- |
| `image_text` | `{ "answer": "A7K2" }` |
| `image_select` | `{ "points": [{ "x": 120, "y": 80 }] }` |
| `slider` | `{ "offset": 138, "trace": [], "duration_ms": 1240 }` |
| `rotate` | `{ "angle": 86, "duration_ms": 960 }` |
| `widget_token` | `{ "token": "...", "provider_response": {} }` |
| `risk_token` | `{ "token": "...", "provider_response": {} }` |

## 能力注册

```php
$manager->register(
    'captcha_vendor',
    'captcha',
    InjectDefinition::make('vendor_slider')
        ->captchaDefinition(CaptchaDefinition::make(
            'vendor_slider',
            ChallengeType::SLIDER,
            ['create', 'verify', 'refresh'],
            [
                'mode' => 'custom',
                'key' => 'vendor.slider',
                'module' => 'captcha-vendor',
                'expose' => './renderer',
                'pre_auth' => true,
            ],
            ['type' => 'object']
        ))
        ->handler(VendorCaptchaProvider::class)
);
```

提供者实现 `CaptchaInterface`（或 `ChallengeProviderInterface`）的 `definition`, `create`, `verify`, `refresh`。创建结果的 `private_state` 只供服务端保存，不能放入接口响应；宿主会通过 `publicData()` 自动移除。

## 生命周期

后台登录场景使用未认证接口：

- `GET /captcha/challenge` 创建挑战。
- `POST /captcha/challenge/refresh`，提交 `challenge_id` 刷新同一插件提供者的挑战。
- `POST /login` 携带 `{ "captcha": { "challenge_id": "...", "response": {} } }` 完成验证。

挑战默认短期缓存，验证成功后不可重放，连续失败会锁定。提供者创建失败时可以尝试其他已注册提供者；验证阶段禁止回退或切换。

## 渲染器

渲染器元数据使用 `mode: standard|custom`。标准渲染器按 `type` 处理协议响应；自定义渲染器通过前端注册表按 `key` 注册，接收挑战数据并发出 `ready`、`response`、`refresh`、`expired`、`error` 事件。渲染器不能修改 `challenge_id`、场景、提供者引用或过期时间，也不能自行判定验证成功。
