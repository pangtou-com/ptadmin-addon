<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Contracts\Payment\ClosablePaymentInterface;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;
use PTAdmin\Addon\Contracts\Payment\PaymentReadinessInterface;
use PTAdmin\Addon\Contracts\Payment\PreparablePaymentInterface;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentCapabilityReference;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentExecutor;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInput;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteraction;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteractionResult;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentOperation;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentReadinessResult;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentRequirements;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentScene;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentSceneDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentStatus;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentTarget;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\InjectDefinition;
use PTAdmin\Addon\Service\PaymentCatalog;

beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->paymentBasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ptadmin-addon-payment-v2-'.uniqid('', true);
    $addonPath = $this->paymentBasePath.DIRECTORY_SEPARATOR.'addons'.DIRECTORY_SEPARATOR.'PaymentV2';
    File::ensureDirectoryExists($addonPath);
    File::put($addonPath.DIRECTORY_SEPARATOR.'manifest.json', (string) json_encode([
        'version' => '1.0.0',
        'providers' => [],
        'code' => 'payment_v2',
        'name' => 'Payment V2',
    ]));

    $this->app->setBasePath($this->paymentBasePath);
    $this->app['config']->set('app.debug', false);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', static function (): AddonManager {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonInjectsManage::getInstance()->reset();
    PaymentProtocolFake::$contexts = [];
    PaymentProtocolFake::$ready = true;
    PaymentProtocolFake::$createStatus = 'pending';

    $scene = PaymentSceneDefinition::make(
        PaymentScene::QR,
        [PaymentTarget::PC],
        [PaymentInteraction::QR_CODE],
        PaymentOperation::values(),
        [PaymentInput::CURRENCY],
        ['CNY']
    );
    AddonInjectsManage::getInstance()->register(
        'payment_v2',
        'payment',
        InjectDefinition::make('wechat_pay')
            ->title('微信支付')
            ->handler(PaymentProtocolFake::class)
            ->paymentDefinition(PaymentDefinition::make('wechat_pay', [$scene])->title('微信支付'))
    );
});

afterEach(function (): void {
    AddonInjectsManage::getInstance()->reset();
    $this->app->setBasePath($this->originalBasePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', static function (): AddonManager {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    File::deleteDirectory($this->paymentBasePath);
});

it('freezes payment protocol constants and rejects unknown values', function (): void {
    expect(PaymentTarget::values())->toBe(['pc', 'mobile_web', 'wechat_webview', 'alipay_webview', 'mini_program', 'native_app'])
        ->and(PaymentScene::values())->toBe(['qr', 'web', 'h5', 'jsapi', 'mini_program', 'app'])
        ->and(PaymentInteraction::values())->toBe(['qr_code', 'redirect', 'form_submit', 'client_invoke', 'none'])
        ->and(PaymentStatus::values())->toBe(['pending', 'processing', 'succeeded', 'failed', 'closed', 'expired'])
        ->and(fn () => PaymentTarget::assert('desktop'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PaymentSceneDefinition::make('native', ['pc'], ['qr_code'], ['create']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PaymentRequirements::make()->operations(['capture']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AddonInjectsManage::getInstance()->register(
            'payment_v2',
            'payment',
            InjectDefinition::make('legacy_pay')->types(['native'])->handler(stdClass::class)
        ))->toThrow(InvalidArgumentException::class);
});

it('validates client executors and standard interaction payloads', function (): void {
    $executor = PaymentExecutor::make('wechat.jsapi', '1');
    $scene = PaymentSceneDefinition::make(PaymentScene::JSAPI, [PaymentTarget::WECHAT_WEBVIEW], [PaymentInteraction::CLIENT_INVOKE], [PaymentOperation::CREATE], [PaymentInput::PAYER_REFERENCE], ['CNY'], [$executor]);

    expect($scene->toArray()['executors'])->toBe([['executor' => 'wechat.jsapi', 'version' => '1']])
        ->and(PaymentInteractionResult::make(PaymentInteraction::QR_CODE, ['content' => 'weixin://wxpay/123'])->toArray()['type'])->toBe('qr_code')
        ->and(PaymentInteractionResult::make(PaymentInteraction::REDIRECT, ['url' => 'https://pay.example/checkout'])->toArray()['type'])->toBe('redirect')
        ->and(PaymentInteractionResult::make(PaymentInteraction::FORM_SUBMIT, ['url' => 'https://pay.example/form', 'method' => 'POST', 'fields' => ['token' => 'value']])->toArray()['type'])->toBe('form_submit')
        ->and(PaymentInteractionResult::make(PaymentInteraction::CLIENT_INVOKE, ['executor' => 'wechat.jsapi', 'version' => '1', 'parameters' => []])->toArray()['type'])->toBe('client_invoke')
        ->and(fn () => PaymentSceneDefinition::make(PaymentScene::JSAPI, [PaymentTarget::WECHAT_WEBVIEW], [PaymentInteraction::CLIENT_INVOKE], [PaymentOperation::CREATE]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PaymentInteractionResult::make(PaymentInteraction::REDIRECT, ['url' => 'javascript:alert(1)']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PaymentInteractionResult::make(PaymentInteraction::QR_CODE, ['content' => '<script>alert(1)</script>']))->toThrow(InvalidArgumentException::class);
});

it('discovers v2 payment capabilities from explicit caller requirements', function (): void {
    $requirements = PaymentRequirements::make()
        ->target(PaymentTarget::PC)
        ->scenes([PaymentScene::QR, PaymentScene::WEB])
        ->interactions([PaymentInteraction::QR_CODE])
        ->operations([PaymentOperation::CREATE, PaymentOperation::QUERY, PaymentOperation::PARSE_NOTIFY])
        ->currency('CNY');

    $methods = addon_payments()->discover($requirements);

    expect(addon_payments())->toBeInstanceOf(PaymentCatalog::class)
        ->and($methods)->toHaveCount(1)
        ->and($methods[0])->toMatchArray([
            'addon_code' => 'payment_v2',
            'capability_code' => 'wechat_pay',
            'profile_code' => 'default',
            'scene' => 'qr',
            'protocol_version' => 2,
            'interactions' => ['qr_code'],
        ])
        ->and(addon_payments()->discover(PaymentRequirements::make()->target(PaymentTarget::MINI_PROGRAM)))->toBe([])
        ->and(fn () => addon_payments()->discover(PaymentRequirements::make()))->toThrow(InvalidArgumentException::class);
});

it('returns structured readiness for an exact capability reference', function (): void {
    $reference = new PaymentCapabilityReference('payment_v2', 'wechat_pay', 'default', PaymentScene::QR);

    expect(addon_payments()->readiness($reference)->toArray())->toBe([
        'ready' => true,
        'reason_code' => null,
        'message' => null,
    ]);

    PaymentProtocolFake::$ready = false;

    expect(addon_payments()->readiness($reference)->toArray())->toBe([
        'ready' => false,
        'reason_code' => 'configuration_incomplete',
        'message' => '支付配置尚未完成',
    ]);
});

it('keeps exact protocol context across every payment operation', function (): void {
    $reference = new PaymentCapabilityReference('payment_v2', 'wechat_pay', 'merchant_a', PaymentScene::QR);
    $gateway = addon_payments()->gateway($reference);

    expect(method_exists($gateway, 'channel'))->toBeFalse();
    expect(fn () => $gateway->create(['order_no' => 'P1000', 'amount' => 1, 'currency' => 'CNY']))
        ->toThrow(\PTAdmin\Addon\Exception\AddonException::class);

    $gateway->prepare(['order_no' => 'P1001']);
    $created = $gateway->create(['order_no' => 'P1001', 'amount_minor' => 199, 'currency' => 'CNY', 'subject' => '测试订单', 'notify_url' => 'https://example.test/notify']);
    $gateway->query(['order_no' => 'P1001']);
    $gateway->close(['order_no' => 'P1001']);
    $gateway->refund(['order_no' => 'P1001', 'refund_no' => 'R1001']);
    $gateway->queryRefund(['refund_no' => 'R1001']);
    $gateway->parseNotify(['body' => '{}']);
    $gateway->acknowledgeNotify(['success' => true]);

    expect($created->toArray())->toMatchArray([
        'protocol_version' => 2,
        'status' => 'pending',
        'scene' => 'qr',
        'interaction' => ['type' => 'qr_code', 'payload' => ['content' => 'weixin://wxpay/P1001']],
    ])->and(array_keys(PaymentProtocolFake::$contexts))->toEqualCanonicalizing([
        'prepare', 'create', 'query', 'close', 'refund', 'queryRefund', 'parseNotify', 'acknowledgeNotify',
    ]);

    foreach (PaymentProtocolFake::$contexts as $context) {
        expect($context)->toBe($reference->toArray());
    }

    PaymentProtocolFake::$createStatus = 'created';

    expect(fn () => $gateway->create(['order_no' => 'P1002', 'amount_minor' => 199, 'currency' => 'CNY', 'subject' => '测试订单', 'notify_url' => 'https://example.test/notify']))
        ->toThrow(\PTAdmin\Addon\Exception\AddonException::class);
});

final class PaymentProtocolFake implements ClosablePaymentInterface, PreparablePaymentInterface, PaymentReadinessInterface
{
    /** @var array<string, array<string, mixed>> */
    public static $contexts = [];

    /** @var bool */
    public static $ready = true;

    /** @var string */
    public static $createStatus = 'pending';

    public function paymentReadiness(PaymentCapabilityReference $reference): PaymentReadinessResult
    {
        return self::$ready
            ? PaymentReadinessResult::ready()
            : PaymentReadinessResult::notReady('configuration_incomplete', '支付配置尚未完成');
    }

    public function prepare(PreparePaymentRequest $payload): PreparePaymentResult
    {
        $this->remember('prepare', $payload->meta());
        return PreparePaymentResult::fromArray(['ready' => true]);
    }

    public function create(CreatePaymentRequest $payload): CreatePaymentResult
    {
        $this->remember('create', $payload->meta());
        return CreatePaymentResult::fromArray(['protocol_version' => 2, 'status' => self::$createStatus, 'scene' => 'qr', 'interaction' => ['type' => 'qr_code', 'payload' => ['content' => 'weixin://wxpay/'.$payload->get('order_no')]]]);
    }

    public function query(QueryPaymentRequest $payload): QueryPaymentResult
    {
        $this->remember('query', $payload->meta());
        return QueryPaymentResult::fromArray(['order_no' => $payload->get('order_no'), 'status' => 'pending']);
    }

    public function close(ClosePaymentRequest $payload): ClosePaymentResult
    {
        $this->remember('close', $payload->meta());
        return ClosePaymentResult::fromArray(['order_no' => $payload->get('order_no'), 'status' => 'closed']);
    }

    public function refund(RefundPaymentRequest $payload): RefundPaymentResult
    {
        $this->remember('refund', $payload->meta());
        return RefundPaymentResult::fromArray(['refund_no' => $payload->get('refund_no'), 'status' => 'pending']);
    }

    public function queryRefund(QueryRefundRequest $payload): QueryRefundResult
    {
        $this->remember('queryRefund', $payload->meta());
        return QueryRefundResult::fromArray(['refund_no' => $payload->get('refund_no'), 'status' => 'pending']);
    }

    public function parseNotify(ParsePaymentNotifyRequest $payload): ParsePaymentNotifyResult
    {
        $this->remember('parseNotify', $payload->meta());
        return ParsePaymentNotifyResult::fromArray(['event' => 'payment.succeeded', 'status' => 'succeeded']);
    }

    public function acknowledgeNotify(AcknowledgePaymentNotifyRequest $payload): AcknowledgePaymentNotifyResult
    {
        $this->remember('acknowledgeNotify', $payload->meta());
        return AcknowledgePaymentNotifyResult::fromArray(['status_code' => 200, 'body' => 'success']);
    }

    /** @param array<string, mixed> $meta */
    private function remember(string $operation, array $meta): void
    {
        self::$contexts[$operation] = (array) ($meta['payment_context'] ?? []);
    }
}
