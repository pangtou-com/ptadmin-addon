<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentInput extends PaymentProtocolValue
{
    public const ORDER_NO = 'order_no';
    public const AMOUNT_MINOR = 'amount_minor';
    public const CURRENCY = 'currency';
    public const SUBJECT = 'subject';
    public const NOTIFY_URL = 'notify_url';
    public const RETURN_URL = 'return_url';
    public const CLIENT_IP = 'client_ip';
    public const PAYER_REFERENCE = 'payer_reference';

    public static function values(): array
    {
        return [self::ORDER_NO, self::AMOUNT_MINOR, self::CURRENCY, self::SUBJECT, self::NOTIFY_URL, self::RETURN_URL, self::CLIENT_IP, self::PAYER_REFERENCE];
    }
}
