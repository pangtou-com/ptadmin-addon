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
    public function create(CreatePaymentRequest $payload): CreatePaymentResult
    {
        $context = (array) ($payload->meta()['payment_context'] ?? []);

        return CreatePaymentResult::fromArray([
            'protocol_version' => 2,
            'status' => 'pending',
            'scene' => $context['scene'] ?? null,
            'interaction' => [
                'type' => 'form_submit',
                'payload' => [
                    'url' => 'https://pay.example.test/submit',
                    'method' => 'POST',
                    'fields' => ['order_no' => $payload->get('order_no')],
                ],
            ],
            'channel_trade_no' => 'trade-ali-1001',
        ]);
    }

    public function query(QueryPaymentRequest $payload): QueryPaymentResult
    {
        return QueryPaymentResult::fromArray([
            'order_no' => $payload->get('order_no'),
            'status' => 'pending',
        ]);
    }

    public function refund(RefundPaymentRequest $payload): RefundPaymentResult
    {
        return RefundPaymentResult::fromArray([
            'order_no' => $payload->get('order_no'),
            'refund_no' => $payload->get('refund_no'),
            'amount_minor' => $payload->get('amount_minor'),
            'status' => 'pending',
        ]);
    }

    public function queryRefund(QueryRefundRequest $payload): QueryRefundResult
    {
        return QueryRefundResult::fromArray([
            'refund_no' => $payload->get('refund_no'),
            'status' => 'pending',
        ]);
    }

    public function parseNotify(ParsePaymentNotifyRequest $payload): ParsePaymentNotifyResult
    {
        return ParsePaymentNotifyResult::fromArray([
            'event' => 'payment.succeeded',
            'order_no' => data_get($payload->get('body', []), 'order_no'),
            'status' => 'succeeded',
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
