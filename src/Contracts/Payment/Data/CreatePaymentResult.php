<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class CreatePaymentResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'status' => null,
            'scene' => null,
            'action' => null,
            'channel_trade_no' => null,
            'payload' => [],
            'display' => [],
            'expires_at' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
