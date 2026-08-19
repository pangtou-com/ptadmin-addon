<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use PTAdmin\Addon\AesUtil;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Service\CloudMarketPurchaseService;
use PTAdmin\AddonTests\TestCase;

class CloudMarketPurchaseServiceTest extends TestCase
{
    private string $sessionPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionPath = storage_path('app/ptadmin/addon/marketplace-session.dat');
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists(dirname($this->sessionPath));
        $filesystem->put($this->sessionPath, AesUtil::encryptString((string) json_encode([
            'token' => 'Bearer purchase-token',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        $this->app->forgetInstance(CloudMarketPurchaseService::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->sessionPath);

        parent::tearDown();
    }

    public function test_native_purchase_requests_use_download_only_platform_contract(): void
    {
        $this->mockPost('/purchase-order-create', [
            'code' => 'payment',
            'addon_version_id' => 12,
            'idempotency_key' => 'purchase-request-001',
        ], ['contract_version' => 1, 'order_no' => 'CO1001']);
        $this->mockPost('/purchase-payment-create', [
            'order_no' => 'CO1001',
            'channel' => 'wechat_native',
        ], ['contract_version' => 1, 'payment_no' => 'PAY1001']);
        $this->mockPost('/purchase-order-query', [
            'order_no' => 'CO1001',
        ], ['contract_version' => 1, 'status' => 'paying']);
        $this->mockPost('/purchase-order-close', [
            'order_no' => 'CO1001',
        ], ['contract_version' => 1, 'status' => 'closed']);

        $service = $this->app->make(CloudMarketPurchaseService::class);
        self::assertSame('CO1001', $service->createOrder('payment', 12, 'purchase-request-001')['order_no']);
        self::assertSame('PAY1001', $service->createPayment('CO1001', 'wechat_native')['payment_no']);
        self::assertSame('paying', $service->queryOrder('CO1001')['status']);
        self::assertSame('closed', $service->closeOrder('CO1001')['status']);
    }

    public function test_license_code_download_does_not_require_cloud_account_session(): void
    {
        @unlink($this->sessionPath);

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();
        Http::shouldReceive('withOptions')->once()->andReturnSelf();
        $response = \Mockery::mock();
        $response->shouldReceive('status')->once()->andReturn(200);
        $response->shouldReceive('json')->once()->andReturn([
            'code' => 0,
            'message' => 'ok',
            'data' => ['url' => 'https://www.pangtou.com/api-addon/download?token=download-token'],
        ]);
        $response->shouldReceive('json')->once()->with('data')->andReturn([
            'url' => 'https://www.pangtou.com/api-addon/download?token=download-token',
        ]);
        Http::shouldReceive('post')->once()->withArgs(function (string $url, array $payload): bool {
            return 'https://www.pangtou.com/api-addon/download' === $url
                && 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV' === ($payload['license_code'] ?? null)
                && isset($payload['time'], $payload['state'], $payload['sign']);
        })->andReturn($response);

        $result = AddonApi::getAddonDownloadUrlByLicenseCode([
            'license_code' => 'PTL-1234567890ABCDEFGHIJKLMNOPQRSTUV',
        ]);

        self::assertSame(
            'https://www.pangtou.com/api-addon/download?token=download-token',
            $result['url']
        );
    }

    /** @param array<string, mixed> $expectedFields @param array<string, mixed> $data */
    private function mockPost(string $path, array $expectedFields, array $data): void
    {
        Http::shouldReceive('withHeaders')->once()->andReturnSelf();
        Http::shouldReceive('withToken')->once()->with('purchase-token')->andReturnSelf();
        Http::shouldReceive('withOptions')->once()->andReturnSelf();
        $response = \Mockery::mock();
        $response->shouldReceive('status')->once()->andReturn(200);
        $response->shouldReceive('json')->once()->andReturn(['code' => 0, 'message' => 'ok', 'data' => $data]);
        $response->shouldReceive('json')->once()->with('data')->andReturn($data);
        Http::shouldReceive('post')->once()->withArgs(function (string $url, array $payload) use ($path, $expectedFields): bool {
            foreach ($expectedFields as $field => $value) {
                if (($payload[$field] ?? null) !== $value) {
                    return false;
                }
            }

            return 'https://www.pangtou.com/api-addon'.$path === $url;
        })->andReturn($response);
    }
}
