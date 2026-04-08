<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Notify;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 消息通知能力接口。
 *
 * 适用于站内消息、邮件、Webhook、企业微信、钉钉等通知通道。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface NotifyInterface extends CapabilityInterface
{
    /**
     * 发送单条消息。
     *
     * 输入字段约定：
     * - channel: 通知渠道标识
     * - receiver: 单个接收人标识
     * - template: 模板标识，不使用模板时传 null
     * - subject: 标题，不需要时传 null
     * - message: 消息正文
     * - data: 模板变量数据
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     message_id:?string,
     *     batch_id:?string,
     *     status:string,
     *     accepted_at:?string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function send(InjectPayload $payload): array;

    /**
     * 批量发送消息。
     *
     * 输入字段约定：
     * - channel: 通知渠道标识
     * - receivers: 接收人列表
     * - template: 模板标识，不使用模板时传 null
     * - subject: 标题，不需要时传 null
     * - message: 消息正文
     * - data: 模板变量数据
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     message_id:?string,
     *     batch_id:?string,
     *     status:string,
     *     accepted_at:?string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function sendBatch(InjectPayload $payload): array;

    /**
     * 查询消息发送状态。
     *
     * 输入字段约定：
     * - message_id: 单条消息 ID，不适用时传 null
     * - batch_id: 批次 ID，不适用时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     message_id:?string,
     *     batch_id:?string,
     *     status:string,
     *     accepted_at:?string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function query(InjectPayload $payload): array;

    /**
     * 解析通知平台回调。
     *
     * 输入字段约定：
     * - body: 原始请求体
     * - headers: 请求头数组
     * - query: URL 查询参数
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     message_id:?string,
     *     batch_id:?string,
     *     receiver:?string,
     *     status:string,
     *     delivered_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function parseCallback(InjectPayload $payload): array;
}
