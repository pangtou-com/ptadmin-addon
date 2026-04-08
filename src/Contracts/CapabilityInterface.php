<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

/**
 * 能力型插件的基础约定。
 *
 * 插件通过 inject 暴露能力时，建议实现该接口，用于声明当前能力类
 * 支持哪些动作，例如 create、refund、send、query 等。
 *
 * 输入字段与输出字段应按能力契约固定。
 * 渠道特有的输入数据统一放入 meta。
 * 渠道原始响应统一放入 raw，渠道扩展输出统一放入 meta。
 */
interface CapabilityInterface
{
    /**
     * 判断当前能力实现是否支持指定动作。
     *
     * 不支持时应返回 false，宿主侧会在执行前拦截调用。
     */
    public function supports(string $operation): bool;
}
