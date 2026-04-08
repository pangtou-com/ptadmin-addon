<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Data;

use PTAdmin\Addon\Support\ArrayData;

class ParsePaymentNotifyRequest extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'body' => null,
            'headers' => [],
            'query' => [],
            'meta' => [],
        ];
    }
}
