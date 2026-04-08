<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Sms;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 短信能力接口。
 *
 * 适用于阿里云短信、腾讯云短信、华为云短信等实现。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface SmsInterface extends CapabilityInterface
{
    /**
     * 发送单条短信。
     *
     * 输入字段约定：
     * - mobile: 手机号
     * - template: 模板标识
     * - sign: 短信签名
     * - data: 模板变量
     * - scene: 业务场景，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     biz_id:?string,
     *     message_id:?string,
     *     status:string,
     *     sent_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function send(InjectPayload $payload): array;

    /**
     * 批量发送短信。
     *
     * 输入字段约定：
     * - mobiles: 手机号列表
     * - template: 模板标识
     * - sign: 短信签名
     * - data: 模板变量
     * - scene: 业务场景，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     biz_id:?string,
     *     batch_id:?string,
     *     status:string,
     *     success_count:int,
     *     fail_count:int,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function sendBatch(InjectPayload $payload): array;

    /**
     * 查询短信发送结果。
     *
     * 输入字段约定：
     * - biz_id: 供应商业务号，不适用时传 null
     * - message_id: 消息 ID，不适用时传 null
     * - mobile: 手机号
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     biz_id:?string,
     *     message_id:?string,
     *     mobile:string,
     *     status:string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function query(InjectPayload $payload): array;

    /**
     * 解析短信状态回执。
     *
     * 输入字段约定：
     * - body: 原始请求体
     * - headers: 请求头数组
     * - query: URL 查询参数
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     biz_id:?string,
     *     message_id:?string,
     *     mobile:?string,
     *     status:string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function parseReceipt(InjectPayload $payload): array;
}
