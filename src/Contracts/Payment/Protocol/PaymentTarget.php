<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

final class PaymentTarget extends PaymentProtocolValue
{
    public const PC = 'pc';
    public const MOBILE_WEB = 'mobile_web';
    public const WECHAT_WEBVIEW = 'wechat_webview';
    public const ALIPAY_WEBVIEW = 'alipay_webview';
    public const MINI_PROGRAM = 'mini_program';
    public const NATIVE_APP = 'native_app';

    public static function values(): array
    {
        return [self::PC, self::MOBILE_WEB, self::WECHAT_WEBVIEW, self::ALIPAY_WEBVIEW, self::MINI_PROGRAM, self::NATIVE_APP];
    }
}
