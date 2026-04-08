<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class QueryRefundResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'refund_no' => null,
            'channel_refund_no' => null,
            'status' => null,
            'refunded_at' => null,
            'amount' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
