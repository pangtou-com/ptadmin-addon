<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentResult;

/**
 * 支持主动关闭支付单的可选支付能力。
 *
 * 单独扩展 PaymentInterface，避免新增关闭动作时破坏已有支付插件兼容性。
 */
interface ClosablePaymentInterface extends PaymentInterface
{
    public function close(ClosePaymentRequest $payload): ClosePaymentResult;
}
