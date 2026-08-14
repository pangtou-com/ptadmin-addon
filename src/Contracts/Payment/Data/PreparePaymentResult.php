<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class PreparePaymentResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'protocol_version' => 2,
            'ready' => false,
            'payer_reference' => null,
            'interaction' => null,
            'expires_at' => null,
            'meta' => [],
            'raw' => null,
        ];
    }
}
