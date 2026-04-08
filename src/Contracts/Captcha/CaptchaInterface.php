<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Captcha;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 图形验证码能力接口。
 *
 * 适用于图片验证码、行为验证码、滑块验证码等实现。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface CaptchaInterface extends CapabilityInterface
{
    /**
     * 生成验证码。
     *
     * 输入字段约定：
     * - scene: 验证场景
     * - type: 验证码类型
     * - width: 宽度，不需要时传 null
     * - height: 高度，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     key:string,
     *     type:string,
     *     content:array,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function generate(InjectPayload $payload): array;

    /**
     * 校验验证码。
     *
     * 输入字段约定：
     * - key: 验证码键
     * - code: 用户输入验证码，不需要时传 null
     * - token: 行为验证码票据，不需要时传 null
     * - meta: 渠道专属扩展参数
     */
    public function verify(InjectPayload $payload): bool;

    /**
     * 刷新验证码。
     *
     * 输入字段约定：
     * - key: 原验证码键，不需要时传 null
     * - scene: 验证场景
     * - type: 验证码类型
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     key:string,
     *     type:string,
     *     content:array,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function refresh(InjectPayload $payload): array;
}
