<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class AcknowledgePaymentNotifyRequest extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'success' => false,
            'message' => null,
            'meta' => [],
        ];
    }
}
