<?php

declare(strict_types=1);

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Contracts\AI\AIInterface;
use PTAdmin\Addon\Contracts\Auth\AuthInterface;
use PTAdmin\Addon\Contracts\Captcha\CaptchaInterface;
use PTAdmin\Addon\Contracts\Logistics\LogisticsInterface;
use PTAdmin\Addon\Contracts\Notify\NotifyInterface;
use PTAdmin\Addon\Contracts\Payment\PaymentInterface;
use PTAdmin\Addon\Contracts\Sms\SmsInterface;
use PTAdmin\Addon\Contracts\Storage\StorageInterface;

it('defines inject contracts for common capability groups', function (): void {
    expect(interface_exists(PaymentInterface::class))->toBeTrue()
        ->and(interface_exists(CapabilityInterface::class))->toBeTrue()
        ->and(interface_exists(AuthInterface::class))->toBeTrue()
        ->and(interface_exists(NotifyInterface::class))->toBeTrue()
        ->and(interface_exists(StorageInterface::class))->toBeTrue()
        ->and(interface_exists(SmsInterface::class))->toBeTrue()
        ->and(interface_exists(AIInterface::class))->toBeTrue()
        ->and(interface_exists(CaptchaInterface::class))->toBeTrue()
        ->and(interface_exists(LogisticsInterface::class))->toBeTrue();
});

it('defines realistic operations for common capability contracts', function (): void {
    expect(get_class_methods(PaymentInterface::class))->toEqualCanonicalizing([
        'supports',
        'create',
        'query',
        'refund',
        'queryRefund',
        'parseNotify',
        'acknowledgeNotify',
    ])->and(get_class_methods(AuthInterface::class))->toEqualCanonicalizing([
        'supports',
        'getAuthorizeUrl',
        'handleCallback',
        'getUser',
        'refreshToken',
    ])->and(get_class_methods(NotifyInterface::class))->toEqualCanonicalizing([
        'supports',
        'send',
        'sendBatch',
        'query',
        'parseCallback',
    ])->and(get_class_methods(StorageInterface::class))->toEqualCanonicalizing([
        'supports',
        'upload',
        'delete',
        'exists',
        'temporaryUrl',
    ])->and(get_class_methods(SmsInterface::class))->toEqualCanonicalizing([
        'supports',
        'send',
        'sendBatch',
        'query',
        'parseReceipt',
    ])->and(get_class_methods(AIInterface::class))->toEqualCanonicalizing([
        'supports',
        'chat',
        'generate',
        'embedding',
    ])->and(get_class_methods(CaptchaInterface::class))->toEqualCanonicalizing([
        'supports',
        'generate',
        'verify',
        'refresh',
    ])->and(get_class_methods(LogisticsInterface::class))->toEqualCanonicalizing([
        'supports',
        'query',
        'subscribe',
        'parseCallback',
    ]);
});
