<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class RefundPaymentRequest extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'order_no' => null,
            'refund_no' => null,
            'amount_minor' => null,
            'currency' => null,
            'reason' => null,
            'meta' => [],
        ];
    }
}
