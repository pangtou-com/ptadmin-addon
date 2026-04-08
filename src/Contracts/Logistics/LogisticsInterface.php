<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Logistics;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 物流能力接口。
 *
 * 适用于物流轨迹查询、运单订阅、物流状态推送解析等实现。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface LogisticsInterface extends CapabilityInterface
{
    /**
     * 查询物流轨迹。
     *
     * 输入字段约定：
     * - shipper_code: 物流公司编码
     * - tracking_no: 运单号
     * - mobile: 收件人手机号后四位等辅助信息，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     shipper_code:string,
     *     tracking_no:string,
     *     status:string,
     *     signed:bool,
     *     current:array,
     *     traces:array,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function query(InjectPayload $payload): array;

    /**
     * 订阅物流状态推送。
     *
     * 输入字段约定：
     * - shipper_code: 物流公司编码
     * - tracking_no: 运单号
     * - callback_url: 回调地址
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     subscribe_id:?string,
     *     status:string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function subscribe(InjectPayload $payload): array;

    /**
     * 解析物流平台回调。
     *
     * 输入字段约定：
     * - body: 原始请求体
     * - headers: 请求头数组
     * - query: URL 查询参数
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     shipper_code:?string,
     *     tracking_no:?string,
     *     status:string,
     *     signed:bool,
     *     current:array,
     *     traces:array,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function parseCallback(InjectPayload $payload): array;
}
