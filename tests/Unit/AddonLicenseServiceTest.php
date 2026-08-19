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
use PTAdmin\Addon\Service\AddonLicenseService;
use PTAdmin\Addon\Service\AddonPackageValidator;
use PTAdmin\AddonTests\TestCase;

class AddonLicenseServiceTest extends TestCase
{
    private string $licenseDirectory;
    private string $sessionPath;
    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseDirectory = storage_path('framework/testing/addon-licenses');
        $this->sessionPath = storage_path('app/ptadmin/addon/marketplace-session.dat');
        (new Filesystem())->deleteDirectory($this->licenseDirectory);
        config()->set('addon.license_storage_path', $this->licenseDirectory);
        config()->set('app.name', '授权测试应用');
        config()->set('app.url', 'https://license.example.com/admin');
        $this->writeMarketplaceSession();
        $this->createInstanceProvider();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->licenseDirectory);
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

    public function test_network_failure_uses_offline_grace_but_expired_grace_blocks_runtime(): void
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

        $this->mockNetworkFailure('/license-verify', false);
        $service->assertCanRun('demo-addon');
        self::assertTrue(true);

        $license['valid_until'] = time() - 1;
        file_put_contents($path, json_encode($license, JSON_THROW_ON_ERROR));
        $this->mockNetworkFailure('/license-verify', false);

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('www.pangtou.com');
        $service->assertCanRun('demo-addon');
    }

    public function test_required_license_blocks_runtime_without_local_activation(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('需要应用实例授权');
        app(AddonLicenseService::class)->assertCanRun('demo-addon');
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
        self::assertTrue(true);
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

    public function test_required_license_is_blocked_before_provider_boot_without_activation(): void
    {
        Addon::swap(new class {
            public function getAddons(): array
            {
                return ['demo-addon' => ['license_required' => true]];
            }
        });

        $this->expectException(AddonException::class);
        app(AddonLicenseService::class)->assertCanBoot('demo-addon');
    }

    /** @param array<string, mixed> $expectedFields @param array<string, mixed> $data */
    private function mockPost(string $path, array $expectedFields, array $data, bool $needLogin, ?callable $validator = null): void
    {
        Http::shouldReceive('withHeaders')->once()->andReturnSelf();
        if ($needLogin) {
            Http::shouldReceive('withToken')->once()->with('test-token')->andReturnSelf();
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
