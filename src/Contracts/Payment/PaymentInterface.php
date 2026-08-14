<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;

/**
 * 支付能力接口。
 *
 * 适用于微信支付、支付宝、银联等支付渠道实现。
 * 统一通过 group=payment 注册，并通过 PaymentDefinition 声明场景和操作。
 *
 * 网关通过 meta.payment_context 传递固定的插件、能力、配置档案、场景和协议版本。
 * 渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface PaymentInterface
{
    /**
     * 发起支付并生成拉起参数。
     *
     * 输入字段约定：
     * - order_no: 业务订单号
     * - amount_minor: 最小货币单位整数
     * - subject: 订单标题
     * - notify_url: 异步通知地址
     * - return_url: 同步返回地址
     * - payer_reference: 短期付款人引用，不需要时传 null
     * - client_ip: 客户端 IP，不需要时传 null
     * - currency: 币种，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     */
    public function create(CreatePaymentRequest $payload): CreatePaymentResult;

    /**
     * 查询支付单状态。
     *
     * 输入字段约定：
     * - order_no: 业务订单号
     * - channel_trade_no: 渠道交易号，不存在时传 null
     * - meta: 渠道专属扩展参数
     *
     */
    public function query(QueryPaymentRequest $payload): QueryPaymentResult;

    /**
     * 发起退款。
     *
     * 输入字段约定：
     * - order_no: 业务订单号
     * - refund_no: 业务退款单号
     * - amount_minor: 最小货币单位整数
     * - currency: 大写 ISO 币种代码
     * - reason: 退款原因，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     */
    public function refund(RefundPaymentRequest $payload): RefundPaymentResult;

    /**
     * 查询退款状态。
     *
     * 输入字段约定：
     * - refund_no: 业务退款单号
     * - channel_refund_no: 渠道退款单号，不存在时传 null
     * - meta: 渠道专属扩展参数
     *
     */
    public function queryRefund(QueryRefundRequest $payload): QueryRefundResult;

    /**
     * 解析支付或退款异步通知。
     *
     * 输入字段约定：
     * - body: 原始请求体
     * - headers: 请求头数组
     * - query: URL 查询参数
     * - meta: 渠道专属扩展参数
     *
     */
    public function parseNotify(ParsePaymentNotifyRequest $payload): ParsePaymentNotifyResult;

    /**
     * 生成给支付网关的回调确认响应。
     *
     * 输入字段约定：
     * - success: 当前业务是否处理成功
     * - message: 响应说明，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     */
    public function acknowledgeNotify(AcknowledgePaymentNotifyRequest $payload): AcknowledgePaymentNotifyResult;
}
