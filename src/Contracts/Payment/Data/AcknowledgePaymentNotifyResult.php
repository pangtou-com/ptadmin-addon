<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class AcknowledgePaymentNotifyResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'status_code' => 200,
            'headers' => [],
            'body' => '',
            'meta' => [],
            'raw' => null,
        ];
    }
}
