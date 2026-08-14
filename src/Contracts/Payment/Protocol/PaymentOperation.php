<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentOperation extends PaymentProtocolValue
{
    public const PREPARE = 'prepare';
    public const CREATE = 'create';
    public const QUERY = 'query';
    public const CLOSE = 'close';
    public const REFUND = 'refund';
    public const QUERY_REFUND = 'query_refund';
    public const PARSE_NOTIFY = 'parse_notify';
    public const ACKNOWLEDGE_NOTIFY = 'acknowledge_notify';

    public static function values(): array
    {
        return [self::PREPARE, self::CREATE, self::QUERY, self::CLOSE, self::REFUND, self::QUERY_REFUND, self::PARSE_NOTIFY, self::ACKNOWLEDGE_NOTIFY];
    }

    public static function method(string $operation): string
    {
        self::assert($operation, 'operation');

        return self::QUERY_REFUND === $operation ? 'queryRefund' : (self::PARSE_NOTIFY === $operation ? 'parseNotify' : (self::ACKNOWLEDGE_NOTIFY === $operation ? 'acknowledgeNotify' : $operation));
    }
}
