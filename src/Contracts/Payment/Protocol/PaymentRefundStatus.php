<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentRefundStatus extends PaymentProtocolValue
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CLOSED = 'closed';

    public static function values(): array
    {
        return [self::PENDING, self::PROCESSING, self::SUCCEEDED, self::FAILED, self::CLOSED];
    }
}
