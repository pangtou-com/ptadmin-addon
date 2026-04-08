<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class ParsePaymentNotifyResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'event' => null,
            'order_no' => null,
            'refund_no' => null,
            'channel_trade_no' => null,
            'channel_refund_no' => null,
            'status' => null,
            'amount' => null,
            'paid_at' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
