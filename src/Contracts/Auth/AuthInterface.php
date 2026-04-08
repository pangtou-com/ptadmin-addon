<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Auth;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 第三方登录能力接口。
 *
 * 适用于 OAuth、OpenID Connect、微信登录、QQ 登录等实现。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface AuthInterface extends CapabilityInterface
{
    /**
     * 生成授权跳转地址。
     *
     * 输入字段约定：
     * - redirect_url: 回调地址
     * - state: 防重放状态值
     * - scope: 授权范围
     * - scene: 登录场景
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     url:string,
     *     state:string,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function getAuthorizeUrl(InjectPayload $payload): array;

    /**
     * 处理授权回调并换取访问令牌。
     *
     * 输入字段约定：
     * - code: 回调授权码
     * - state: 回调状态值
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     openid:?string,
     *     unionid:?string,
     *     access_token:string,
     *     refresh_token:?string,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function handleCallback(InjectPayload $payload): array;

    /**
     * 获取第三方用户资料。
     *
     * 输入字段约定：
     * - access_token: 访问令牌
     * - openid: 第三方用户标识，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     openid:?string,
     *     unionid:?string,
     *     nickname:?string,
     *     avatar:?string,
     *     email:?string,
     *     mobile:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function getUser(InjectPayload $payload): array;

    /**
     * 刷新访问令牌。
     *
     * 输入字段约定：
     * - refresh_token: 刷新令牌
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     access_token:string,
     *     refresh_token:?string,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function refreshToken(InjectPayload $payload): array;
}
