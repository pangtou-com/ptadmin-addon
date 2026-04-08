<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class CreatePaymentRequest extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'scene' => null,
            'order_no' => null,
            'amount' => null,
            'subject' => null,
            'notify_url' => null,
            'return_url' => null,
            'open_id' => null,
            'client_ip' => null,
            'currency' => null,
            'meta' => [],
        ];
    }
}
