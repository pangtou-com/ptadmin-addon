<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentResult;

/**
 * 支持付款人身份或其他支付前置条件准备的可选支付能力。
 */
interface PreparablePaymentInterface extends PaymentInterface
{
    public function prepare(PreparePaymentRequest $payload): PreparePaymentResult;
}
