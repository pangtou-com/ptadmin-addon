# Hook 规范

## 目标

`hook` 用于表达“某个事件发生后，允许一个或多个插件监听并执行扩展逻辑”。

适用场景：

- 支付成功后
- 用户注册后
- 订单创建后
- 内容发布后

## 设计定位

`hook` 是事件订阅机制，不是能力选择机制。

它的职责是：

- 发布事件
- 查找所有监听器
- 按顺序执行监听器

## 推荐做法

- 通过代码注册，不通过 `json` 配置监听器映射
- 使用稳定的事件名，如 `payment.success`、`order.created`
- 监听器处理器使用 `Class@method` 或默认 `handle`

## 最小定义

一个 hook 定义至少应包含：

- `event`
- `handler`
- `priority`

## 代码示意

```php
$manager->register(
    HookDefinition::make('payment.success')
        ->handler(Addon\Order\Listeners\PaymentSuccessListener::class.'@handle')
        ->priority(10)
);
```

触发示意：

```php
Addon::triggerHook('payment.success', [
    'order_id' => 1001,
]);
```

## 当前实现

当前仓库中：

- 注册中心与调度器：`AddonHooksManage`
- 负载对象：`HookPayload`
- 插件注册入口：`BaseBootstrap::registerHooks()`
- 触发入口：`Addon::triggerHook()`

## 使用建议

- 需要广播事件给多个扩展点时使用 `hook`
- 不要用 `hook` 代替主流程中的同步能力调用
- 监听器应尽量保持幂等和可重复执行
