<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class QueryPaymentResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'order_no' => null,
            'channel_trade_no' => null,
            'status' => null,
            'paid_at' => null,
            'amount' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
