<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment;

use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentCapabilityReference;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentReadinessResult;

interface PaymentReadinessInterface
{
    public function paymentReadiness(PaymentCapabilityReference $reference): PaymentReadinessResult;
}
