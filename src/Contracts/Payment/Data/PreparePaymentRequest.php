<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class PreparePaymentRequest extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'order_no' => null,
            'return_url' => null,
            'payer_reference' => null,
            'meta' => [],
        ];
    }
}
