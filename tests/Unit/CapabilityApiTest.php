<?php

declare(strict_types=1);

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Contracts\Auth\AuthInterface;
use PTAdmin\Addon\Contracts\CapabilityReadinessInterface;
use PTAdmin\Addon\Exception\AddonException;
use Illuminate\Support\Facades\File;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\AuthGateway;
use PTAdmin\Addon\Service\CapabilityCatalog;
use PTAdmin\Addon\Service\InjectDefinition;
use PTAdmin\Addon\Service\InjectPayload;
use PTAdmin\Addon\Service\PaymentGateway;

beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->capabilityBasePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-capability-'.uniqid('', true);
    foreach ([
        'FakeAuth' => ['code' => 'fake_auth', 'name' => 'Fake Auth'],
        'LegacyPayment' => ['code' => 'legacy_payment', 'name' => 'Legacy Payment'],
    ] as $directory => $manifest) {
        $addonPath = $this->capabilityBasePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.$directory;
        File::ensureDirectoryExists($addonPath);
        File::put($addonPath.\DIRECTORY_SEPARATOR.'manifest.json', (string) json_encode([
            'version' => '1.0.0',
            'providers' => [],
        ] + $manifest));
    }

    $this->app->setBasePath($this->capabilityBasePath);
    $this->app['config']->set('app.debug', false);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function (): AddonManager {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonInjectsManage::getInstance()->reset();

    AddonInjectsManage::getInstance()->register(
        'fake_auth',
        'auth',
        InjectDefinition::make('fake')
            ->title('Fake Login')
            ->types(['login', 'connect'])
            ->handler(CapabilityApiFakeAuth::class)
    );
    AddonInjectsManage::getInstance()->register(
        'legacy_payment',
        'payment',
        InjectDefinition::make('legacy_pay')
            ->title('Legacy Pay')
            ->types(['web'])
            ->handler(CapabilityApiLegacyPayment::class)
    );
});

afterEach(function (): void {
    AddonInjectsManage::getInstance()->reset();
    $this->app->setBasePath($this->originalBasePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function (): AddonManager {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    File::deleteDirectory($this->capabilityBasePath);
});

it('exposes sanitized registered capability definitions', function (): void {
    $catalog = addon_cap('auth');

    expect($catalog)->toBeInstanceOf(CapabilityCatalog::class)
        ->and($catalog->group())->toBe('auth')
        ->and($catalog->all())->toBe([
            [
                'addon_code' => 'fake_auth',
                'group' => 'auth',
                'code' => 'fake',
                'title' => 'Fake Login',
                'types' => ['login', 'connect'],
            ],
        ])
        ->and($catalog->all()[0])->not->toHaveKey('class')
        ->and($catalog->has('fake'))->toBeTrue()
        ->and($catalog->has('missing'))->toBeFalse()
        ->and($catalog->find('fake', 'fake_auth')['title'])->toBe('Fake Login');
});

it('filters capabilities through optional readiness checks', function (): void {
    expect(addon_cap('auth')->available(['enabled' => true]))->toHaveCount(1)
        ->and(addon_cap('auth')->available(['enabled' => false]))->toBe([])
        ->and(addon_cap('auth')->isAvailable('fake', 'fake_auth', ['enabled' => true]))->toBeTrue()
        ->and(addon_cap('auth')->isAvailable('fake', 'fake_auth', ['enabled' => false]))->toBeFalse();
});

it('keeps registered capabilities without readiness checks compatible', function (): void {
    expect(addon_cap('payment')->available())->toBe([
        [
            'addon_code' => 'legacy_payment',
            'group' => 'payment',
            'code' => 'legacy_pay',
            'title' => 'Legacy Pay',
            'types' => ['web'],
        ],
    ]);
});

it('executes auth capabilities through the public gateway and helper', function (): void {
    $auth = addon_auth('fake', 'fake_auth');

    expect($auth)->toBeInstanceOf(AuthGateway::class)
        ->and($auth->types())->toBe(['login', 'connect'])
        ->and($auth->ready(['enabled' => true]))->toBeTrue()
        ->and($auth->supports('getAuthorizeUrl'))->toBeTrue()
        ->and($auth->getAuthorizeUrl([
            'redirect_url' => 'https://example.com/callback',
            'state' => 'state-token',
        ]))->toMatchArray([
            'url' => 'https://identity.example/authorize?state=state-token',
            'state' => 'state-token',
        ]);
});

it('rejects unsupported auth operations through the gateway', function (): void {
    expect(fn () => addon_auth('fake', 'fake_auth')->refreshToken([]))
        ->toThrow(AddonException::class);
});

it('provides a payment gateway helper without changing payment selection rules', function (): void {
    expect(addon_payment('legacy_pay', 'legacy_payment'))->toBeInstanceOf(PaymentGateway::class)
        ->and(Addon::capabilities('payment'))->toBeInstanceOf(CapabilityCatalog::class)
        ->and(Addon::auth('fake_auth', 'fake'))->toBeInstanceOf(AuthGateway::class);
});

it('resolves helper gateways by capability code', function (): void {
    expect(addon_auth('fake')->definition()['addon_code'])->toBe('fake_auth')
        ->and(addon_payment('legacy_pay')->definition()['addon_code'])->toBe('legacy_payment');
});

it('rejects ambiguous capability codes unless the addon is specified', function (): void {
    AddonInjectsManage::getInstance()->register(
        'legacy_payment',
        'auth',
        InjectDefinition::make('fake')
            ->title('Another Fake Login')
            ->types(['login'])
            ->handler(CapabilityApiFakeAuth::class)
    );

    expect(addon_cap('auth')->has('fake'))->toBeTrue()
        ->and(fn () => addon_cap('auth')->find('fake'))->toThrow(AddonException::class)
        ->and(fn () => addon_auth('fake')->definition())->toThrow(AddonException::class)
        ->and(addon_auth('fake', 'fake_auth')->definition()['title'])->toBe('Fake Login');
});

it('rejects invalid capability group names', function (): void {
    expect(fn () => addon_cap(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => addon_cap('../auth'))->toThrow(InvalidArgumentException::class);
});

final class CapabilityApiFakeAuth implements AuthInterface, CapabilityReadinessInterface
{
    public function supports(string $operation): bool
    {
        return in_array($operation, ['ready', 'getAuthorizeUrl', 'handleCallback', 'getUser'], true);
    }

    public function ready(InjectPayload $payload): bool
    {
        return true === $payload->get('enabled');
    }

    public function getAuthorizeUrl(InjectPayload $payload): array
    {
        return [
            'url' => 'https://identity.example/authorize?state='.$payload->get('state'),
            'state' => $payload->get('state'),
            'expires_at' => null,
            'meta' => [],
            'raw' => null,
        ];
    }

    public function handleCallback(InjectPayload $payload): array
    {
        return ['access_token' => (string) $payload->get('code')];
    }

    public function getUser(InjectPayload $payload): array
    {
        return ['openid' => (string) $payload->get('openid')];
    }

    public function refreshToken(InjectPayload $payload): array
    {
        return ['access_token' => (string) $payload->get('refresh_token')];
    }
}

final class CapabilityApiLegacyPayment
{
}
