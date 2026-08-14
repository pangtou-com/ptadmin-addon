<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentResult;

/**
 * 支持主动关闭支付单的可选支付能力。
 *
 * 插件声明 close 操作时必须实现本接口。
 */
interface ClosablePaymentInterface extends PaymentInterface
{
    public function close(ClosePaymentRequest $payload): ClosePaymentResult;
}
