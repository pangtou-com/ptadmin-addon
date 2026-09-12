<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PTAdmin\Addon\AesUtil;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Contracts\ApplicationInstanceProviderInterface;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonInstallationRegistry;
use PTAdmin\Addon\Service\AddonLicenseService;
use PTAdmin\Addon\Service\AddonPackageValidator;
use PTAdmin\AddonTests\TestCase;

class AddonLicenseServiceTest extends TestCase
{
    private string $licenseDirectory;
    private string $runtimeDirectory;
    private string $deliveryDirectory;
    private string $sessionPath;
    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseDirectory = storage_path('framework/testing/addon-licenses');
        $this->runtimeDirectory = storage_path('framework/testing/addon-runtime');
        $this->deliveryDirectory = storage_path('framework/testing/addon-delivery');
        $this->sessionPath = storage_path('app/ptadmin/addon/marketplace-session.dat');
        (new Filesystem())->deleteDirectory($this->licenseDirectory);
        (new Filesystem())->deleteDirectory($this->runtimeDirectory);
        (new Filesystem())->deleteDirectory($this->deliveryDirectory);
        config()->set('addon.license_storage_path', $this->licenseDirectory);
        config()->set('addon.license_runtime_storage_path', $this->runtimeDirectory);
        config()->set('app.name', '授权测试应用');
        config()->set('app.url', 'https://license.example.com/admin');
        $this->writeMarketplaceSession();
        $this->createInstanceProvider();
        config()->set('addon.platform_license_public_key', $this->publicKey);
        app(AddonInstallationRegistry::class)->forget('demo-addon');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->licenseDirectory);
        (new Filesystem())->deleteDirectory($this->runtimeDirectory);
        (new Filesystem())->deleteDirectory($this->deliveryDirectory);
        app(AddonInstallationRegistry::class)->forget('demo-addon');
        @unlink($this->sessionPath);

        parent::tearDown();
    }

    public function test_activation_is_persisted_and_runtime_verification_is_signed(): void
    {
        $this->mockPost('/license-activate', [
            'license_code' => 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV',
            'code' => 'demo-addon',
            'application_instance_id' => 'pt_test_instance',
        ], [
            'license_id' => 15,
            'purchase_id' => 22,
            'activation_status' => 'active',
            'application_instance_id' => 'pt_test_instance',
            'activation_token' => 'activation-token',
            'verification_interval' => 300,
            'offline_grace_seconds' => 900,
        ], false);
        $this->mockPost('/license-verify', [
            'activation_token' => 'activation-token',
            'code' => 'demo-addon',
            'application_instance_id' => 'pt_test_instance',
        ], [
            'allow_run' => true,
            'reason_code' => 'ACTIVE',
            'valid_until' => time() + 900,
        ], false, function (array $request): bool {
            $timestamp = (int) ($request['timestamp'] ?? 0);
            $payload = implode("\n", ['activation-token', 'demo-addon', 'pt_test_instance', (string) $timestamp]);
            $signature = base64_decode((string) ($request['signature'] ?? ''), true);

            return 'license.example.com' === ($request['domain'] ?? null)
                && is_string($signature)
                && 1 === openssl_verify($payload, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
        });
        $service = app(AddonLicenseService::class);
        $service->activate('demo-addon', 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV');
        $verified = $service->verify('demo-addon');

        self::assertTrue($verified['allow_run']);
        $status = $service->status('demo-addon');
        self::assertSame(15, $status['license_id']);
        self::assertArrayNotHasKey('activation_token', $status);
        self::assertFileExists($this->licenseDirectory.'/demo-addon.json');

    }

    public function test_validate_for_install_checks_license_without_activation_or_local_write(): void
    {
        $licenseCode = 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV';
        $this->mockPost('/license-validate', [
            'code' => 'demo-addon',
            'addon_version_id' => 42,
            'license_code' => $licenseCode,
        ], [
            'valid' => true,
            'code' => 'demo-addon',
            'addon_version_id' => 42,
        ], false);

        $result = app(AddonLicenseService::class)->validateForInstall('demo-addon', 42, $licenseCode);

        self::assertTrue($result['valid']);
        self::assertSame('demo-addon', $result['code']);
        self::assertSame(42, $result['addon_version_id']);
        self::assertFileDoesNotExist($this->licenseDirectory.'/demo-addon.json');
    }

    public function test_validate_for_install_rejects_local_addon_before_platform_request(): void
    {
        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'local_package');

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('本地插件');
        app(AddonLicenseService::class)->validateForInstall(
            'demo-addon',
            42,
            'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV'
        );
    }

    public function test_expired_offline_grace_is_recorded_without_blocking_runtime(): void
    {
        $this->mockPost('/license-activate', ['license_code' => 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV'], [
            'license_id' => 15,
            'purchase_id' => 22,
            'activation_status' => 'active',
            'application_instance_id' => 'pt_test_instance',
            'activation_token' => 'activation-token',
            'verification_interval' => 300,
            'offline_grace_seconds' => 900,
        ], false);
        $service = app(AddonLicenseService::class);
        $service->activate('demo-addon', 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV');
        $path = $this->licenseDirectory.'/demo-addon.json';
        $license = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $license['last_verified_at'] = 0;
        file_put_contents($path, json_encode($license, JSON_THROW_ON_ERROR));

        $service->assertCanRun('demo-addon');
        self::assertTrue(true);

        $license['valid_until'] = time() - 1;
        file_put_contents($path, json_encode($license, JSON_THROW_ON_ERROR));

        $service->assertCanRun('demo-addon');
        $service->assertCanBoot('demo-addon');
        self::assertSame('offline_grace', $service->runtimeStatus('demo-addon')['state']);
    }

    public function test_required_license_waits_for_platform_decision_without_local_activation(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });
        app(AddonInstallationRegistry::class)->markInstalled(
            'demo-addon',
            '1.0.0',
            'marketplace',
            ['release_license_policy' => 'license_required']
        );

        app(AddonLicenseService::class)->assertCanRun('demo-addon');
        app(AddonLicenseService::class)->assertCanBoot('demo-addon');
        self::assertSame('unknown', app(AddonLicenseService::class)->runtimeStatus('demo-addon')['state']);
    }

    public function test_delivery_license_is_verified_from_addon_directory(): void
    {
        $this->fakeDeliveryAddon();
        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'marketplace', [
            'addon_version_id' => 42,
            'package_hash' => 'sha256:'.str_repeat('a', 64),
        ]);
        $this->writeDeliveryLicense();

        $status = app(AddonLicenseService::class)->deliveryStatus('demo-addon');

        self::assertSame('verified', $status['status']);
        self::assertSame(AddonLicenseService::PROTOCOL, $status['protocol']);
        self::assertSame('delivery', $status['kind']);
        self::assertSame(str_repeat('a', 32), $status['delivery_id']);
        self::assertSame(15, $status['license_id']);
        self::assertSame('1.0.0', $status['version']);
        self::assertNotSame('', $status['signature']);
    }

    public function test_delivery_license_problems_are_reported_without_blocking_runtime(): void
    {
        $this->fakeDeliveryAddon();
        $service = app(AddonLicenseService::class);

        self::assertSame('missing', $service->deliveryStatus('demo-addon')['status']);

        $this->writeDeliveryLicense(['signature' => base64_encode('tampered')], false);
        self::assertSame('unverified', $service->deliveryStatus('demo-addon')['status']);

        $this->writeDeliveryLicense(['delivery_id' => 'invalid']);
        self::assertSame('INVALID_FORMAT', $service->deliveryStatus('demo-addon')['reason_code']);

        $this->writeDeliveryLicense(['key_id' => 'unknown-platform-key']);
        self::assertSame('UNSUPPORTED_KEY_ID', $service->deliveryStatus('demo-addon')['reason_code']);

        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'marketplace', [
            'addon_version_id' => 42,
            'package_hash' => 'sha256:'.str_repeat('b', 64),
        ]);
        $this->writeDeliveryLicense();
        self::assertSame('mismatched', $service->deliveryStatus('demo-addon')['status']);
        app(AddonInstallationRegistry::class)->forget('demo-addon');

        $this->writeDeliveryLicense(['expires_at' => time() - 1]);
        self::assertSame('expired', $service->deliveryStatus('demo-addon')['status']);

        $service->assertCanBoot('demo-addon');
        $service->assertCanRun('demo-addon');
        self::assertTrue(true);
    }

    public function test_legacy_addon_without_required_license_remains_compatible(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => false]];
            }
        });

        app(AddonLicenseService::class)->assertCanRun('demo-addon');
        self::assertSame('legacy_review', app(AddonLicenseService::class)->runtimeStatus('demo-addon')['state']);
    }

    public function test_marketplace_install_without_license_metadata_uses_free_perpetual_policy(): void
    {
        $metadata = app(AddonInstallationRegistry::class)->metadataFromManifest([], 'marketplace');

        self::assertSame('free_perpetual', $metadata['release_license_policy']);
    }

    public function test_local_addon_is_exempt_even_when_manifest_declares_platform_license(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });
        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'local_package');

        $runtime = app(AddonLicenseService::class)->runtimeStatus('demo-addon');

        self::assertSame('exempt', $runtime['state']);
        self::assertSame('LOCAL_ADDON', $runtime['reason_code']);
        app(AddonLicenseService::class)->assertCanBoot('demo-addon');
    }

    public function test_local_addon_rejects_platform_runtime_decision(): void
    {
        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'local_package');

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('本地插件');
        app(AddonLicenseService::class)->applyRuntimeDecision($this->signedRuntimeDecision('active'));
    }

    public function test_local_addon_rejects_platform_activation(): void
    {
        app(AddonInstallationRegistry::class)->markInstalled('demo-addon', '1.0.0', 'local_package');

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('本地插件');
        app(AddonLicenseService::class)->activate('demo-addon', 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV');
    }

    public function test_signed_decision_promotes_legacy_installation_to_platform_management(): void
    {
        $registry = app(AddonInstallationRegistry::class);
        $registry->markInstalled('demo-addon', '1.0.0', 'existing', [
            'package_hash' => 'sha256:test-package',
        ]);
        self::assertSame(AddonInstallationRegistry::MANAGEMENT_LEGACY_UNKNOWN, $registry->managementScope('demo-addon'));

        app(AddonLicenseService::class)->applyRuntimeDecision($this->signedRuntimeDecision('active'));

        self::assertSame(AddonInstallationRegistry::MANAGEMENT_PLATFORM, $registry->managementScope('demo-addon'));
        self::assertSame('active', app(AddonLicenseService::class)->runtimeStatus('demo-addon')['state']);
    }

    public function test_required_license_manifest_accepts_supported_protocol(): void
    {
        (new AddonPackageValidator())->validate([
            'code' => 'demo-addon',
            'license_required' => true,
            'license_protocol' => AddonLicenseService::PROTOCOL,
        ]);

        self::assertTrue(true);
    }

    public function test_required_license_manifest_rejects_missing_protocol(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('license_protocol');

        (new AddonPackageValidator())->validate([
            'code' => 'demo-addon',
            'license_required' => true,
        ]);
    }

    public function test_expired_license_grace_is_recorded_without_blocking_startup(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });

        $service = app(AddonLicenseService::class);
        $service->applyRuntimeDecision($this->signedRuntimeDecision('grace', [
            'reason_code' => 'LICENSE_REQUIRED',
            'grace_started_at' => time() - 7200,
            'grace_ends_at' => time() - 1,
        ]));

        $service->assertCanBoot('demo-addon');
        $service->assertCanRun('demo-addon');
        self::assertSame('blocked', $service->runtimeStatus('demo-addon')['state']);
        self::assertSame('GRACE_EXPIRED', $service->runtimeStatus('demo-addon')['reason_code']);
    }

    public function test_signed_free_release_decision_remains_exempt_after_market_price_changes(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => [
                    'license_required' => true,
                    'release_license_policy' => 'free_perpetual',
                ]];
            }
        });

        app(AddonLicenseService::class)->applyRuntimeDecision($this->signedRuntimeDecision('exempt', [
            'reason_code' => 'FREE_GRANDFATHERED',
        ]));
        app(AddonLicenseService::class)->assertCanBoot('demo-addon');
        self::assertSame('exempt', app(AddonLicenseService::class)->runtimeStatus('demo-addon')['state']);
    }

    public function test_tampered_runtime_decision_is_not_used_to_exempt_addon(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });

        $decision = $this->signedRuntimeDecision('exempt', ['reason_code' => 'FREE_GRANDFATHERED']);
        $decision['reason_code'] = 'TAMPERED';

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('签名无效');
        app(AddonLicenseService::class)->applyRuntimeDecision($decision);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function signedRuntimeDecision(string $state, array $overrides = []): array
    {
        $decision = array_merge([
            'protocol' => AddonLicenseService::RUNTIME_PROTOCOL,
            'application_instance_id' => 'pt_test_instance',
            'addon_code' => 'demo-addon',
            'version' => '1.0.0',
            'package_hash' => 'sha256:test-package',
            'state' => $state,
            'reason_code' => 'ACTIVE',
            'grace_started_at' => 0,
            'grace_ends_at' => 0,
            'valid_until' => time() + 86400,
            'issued_at' => time(),
            'policy_version' => 1,
        ], $overrides);
        $payload = json_encode($decision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $decision['signature'] = base64_encode($signature);

        return $decision;
    }

    private function fakeDeliveryAddon(): void
    {
        $directory = $this->deliveryDirectory;
        (new Filesystem())->ensureDirectoryExists($directory.'/.ptadmin');
        Addon::swap(new class($directory) {
            private string $directory;

            public function __construct(string $directory)
            {
                $this->directory = $directory;
            }

            public function getAddonPath(string $code, ?string $path = null): string
            {
                return $this->directory.(null === $path ? '' : DIRECTORY_SEPARATOR.$path);
            }

            public function getAddonVersion(string $code): string
            {
                return '1.0.0';
            }
        });
    }

    /** @param array<string, mixed> $overrides */
    private function writeDeliveryLicense(array $overrides = [], bool $sign = true): void
    {
        $license = array_merge([
            'protocol' => AddonLicenseService::PROTOCOL,
            'kind' => 'delivery',
            'delivery_id' => str_repeat('a', 32),
            'license_id' => 15,
            'addon_code' => 'demo-addon',
            'addon_version_id' => 42,
            'version' => '1.0.0',
            'artifact_hash' => 'sha256:'.str_repeat('a', 64),
            'license_scope' => 'perpetual',
            'expires_at' => 0,
            'issued_at' => time(),
            'key_id' => 'platform-default',
            'signature' => '',
        ], $overrides);
        if ($sign) {
            $payload = $license;
            unset($payload['signature']);
            openssl_sign(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $signature,
                $this->privateKey,
                OPENSSL_ALGO_SHA256
            );
            $license['signature'] = base64_encode($signature);
        }

        (new Filesystem())->put(
            $this->deliveryDirectory.'/.ptadmin/license.json',
            json_encode($license, JSON_THROW_ON_ERROR)
        );
    }

    /** @param array<string, mixed> $expectedFields @param array<string, mixed> $data */
    private function mockPost(string $path, array $expectedFields, array $data, bool $needLogin, ?callable $validator = null): void
    {
        Http::shouldReceive('withHeaders')->once()->andReturnSelf();
        if ($needLogin) {
            Http::shouldReceive('withToken')->once()->with('test-token')->andReturnSelf();
        } else {
            Http::shouldReceive('withToken')->never();
        }
        Http::shouldReceive('withOptions')->once()->andReturnSelf();
        $response = \Mockery::mock();
        $response->shouldReceive('status')->once()->andReturn(200);
        $response->shouldReceive('json')->once()->andReturn(['code' => 0, 'message' => 'ok', 'data' => $data]);
        $response->shouldReceive('json')->once()->with('data')->andReturn($data);
        Http::shouldReceive('post')->once()->withArgs(function (string $url, array $payload) use ($path, $expectedFields, $validator): bool {
            foreach ($expectedFields as $field => $value) {
                if (($payload[$field] ?? null) !== $value) {
                    return false;
                }
            }

            return 'https://www.pangtou.com/api-addon'.$path === $url
                && (null === $validator || (bool) $validator($payload));
        })->andReturn($response);
    }

    private function mockNetworkFailure(string $path, bool $needLogin): void
    {
        Http::shouldReceive('withHeaders')->once()->andReturnSelf();
        if ($needLogin) {
            Http::shouldReceive('withToken')->once()->with('test-token')->andReturnSelf();
        }
        Http::shouldReceive('withOptions')->once()->andReturnSelf();
        Http::shouldReceive('post')->once()->withArgs(function (string $url) use ($path): bool {
            return 'https://www.pangtou.com/api-addon'.$path === $url;
        })->andThrow(new ConnectionException('platform unavailable'));
    }

    private function createInstanceProvider(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        openssl_pkey_export($key, $this->privateKey);
        $this->publicKey = (string) openssl_pkey_get_details($key)['key'];
        $privateKey = $this->privateKey;
        $publicKey = $this->publicKey;

        app()->instance(ApplicationInstanceProviderInterface::class, new class($privateKey, $publicKey) implements ApplicationInstanceProviderInterface {
            private string $privateKey;
            private string $publicKey;

            public function __construct(string $privateKey, string $publicKey)
            {
                $this->privateKey = $privateKey;
                $this->publicKey = $publicKey;
            }

            public function applicationInstanceId(): string
            {
                return 'pt_test_instance';
            }

            public function publicKey(): string
            {
                return $this->publicKey;
            }

            public function sign(string $payload): string
            {
                openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

                return base64_encode($signature);
            }
        });
        app()->forgetInstance(AddonLicenseService::class);
    }

    private function writeMarketplaceSession(): void
    {
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists(dirname($this->sessionPath));
        $filesystem->put($this->sessionPath, AesUtil::encryptString((string) json_encode([
            'token' => 'Bearer test-token',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }
}
