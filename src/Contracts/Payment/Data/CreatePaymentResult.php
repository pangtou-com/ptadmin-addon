<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class CreatePaymentResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'protocol_version' => null,
            'status' => null,
            'scene' => null,
            'interaction' => null,
            'channel_trade_no' => null,
            'expires_at' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
