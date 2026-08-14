<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentScene extends PaymentProtocolValue
{
    public const QR = 'qr';
    public const WEB = 'web';
    public const H5 = 'h5';
    public const JSAPI = 'jsapi';
    public const MINI_PROGRAM = 'mini_program';
    public const APP = 'app';

    public static function values(): array
    {
        return [self::QR, self::WEB, self::H5, self::JSAPI, self::MINI_PROGRAM, self::APP];
    }
}
