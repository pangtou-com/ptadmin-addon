<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentInteraction extends PaymentProtocolValue
{
    public const QR_CODE = 'qr_code';
    public const REDIRECT = 'redirect';
    public const FORM_SUBMIT = 'form_submit';
    public const CLIENT_INVOKE = 'client_invoke';
    public const NONE = 'none';

    public static function values(): array
    {
        return [self::QR_CODE, self::REDIRECT, self::FORM_SUBMIT, self::CLIENT_INVOKE, self::NONE];
    }
}
