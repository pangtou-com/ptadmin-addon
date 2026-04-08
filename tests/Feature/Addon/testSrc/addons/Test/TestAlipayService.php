<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test;

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
use PTAdmin\Addon\Contracts\Payment\PaymentInterface;

class TestAlipayService implements PaymentInterface
{
    public function supports(string $operation): bool
    {
        return \in_array($operation, [
            'create',
            'query',
            'refund',
            'queryRefund',
            'parseNotify',
            'acknowledgeNotify',
        ], true);
    }

    public function create(CreatePaymentRequest $payload): CreatePaymentResult
    {
        return CreatePaymentResult::fromArray([
            'status' => 'created',
            'scene' => $payload->get('scene'),
            'action' => 'form',
            'channel_trade_no' => 'trade-ali-1001',
            'payload' => [
                'order_no' => $payload->get('order_no'),
            ],
            'display' => [
                'form' => '<form id="alipay"></form>',
            ],
        ]);
    }

    public function query(QueryPaymentRequest $payload): QueryPaymentResult
    {
        return QueryPaymentResult::fromArray([
            'order_no' => $payload->get('order_no'),
            'status' => 'paid',
        ]);
    }

    public function refund(RefundPaymentRequest $payload): RefundPaymentResult
    {
        return RefundPaymentResult::fromArray([
            'order_no' => $payload->get('order_no'),
            'refund_no' => $payload->get('refund_no'),
            'amount' => $payload->get('amount'),
            'status' => 'success',
        ]);
    }

    public function queryRefund(QueryRefundRequest $payload): QueryRefundResult
    {
        return QueryRefundResult::fromArray([
            'refund_no' => $payload->get('refund_no'),
            'status' => 'success',
        ]);
    }

    public function parseNotify(ParsePaymentNotifyRequest $payload): ParsePaymentNotifyResult
    {
        return ParsePaymentNotifyResult::fromArray([
            'event' => 'payment.paid',
            'order_no' => data_get($payload->get('body', []), 'order_no'),
            'status' => 'paid',
        ]);
    }

    public function acknowledgeNotify(AcknowledgePaymentNotifyRequest $payload): AcknowledgePaymentNotifyResult
    {
        return AcknowledgePaymentNotifyResult::fromArray([
            'status_code' => 200,
            'body' => $payload->get('success', true) ? 'success' : 'fail',
        ]);
    }
}
