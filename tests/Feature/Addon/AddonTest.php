<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2025 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\AddonTests\Feature\Addon;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use FilesystemIterator;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonConfigManager;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\Action\AddonAction;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class FakeAdminResourceServiceForAddonTest
{
    public array $synced = [];
    public array $disabled = [];
    public array $deleted = [];

    public function syncAddonResources(string $addonCode, array $definitions): void
    {
        $this->synced[] = array(
            'addon_code' => $addonCode,
            'definitions' => $definitions,
        );
    }

    public function disableAddonResources(string $addonCode): void
    {
        $this->disabled[] = $addonCode;
    }

    public function deleteByAddonCode(string $addonCode): void
    {
        $this->deleted[] = $addonCode;
    }
}

beforeEach(function (): void {
    $app = $this->app;
    $app->setBasePath(__DIR__.\DIRECTORY_SEPARATOR.'testSrc');
    $app->forgetInstance('addon');
    $app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'disable');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'enable.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'disable.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'upgrade.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'install.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'init.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'uninstall.log');
    @unlink(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addon-uninstall.log');
    touch(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2'.\DIRECTORY_SEPARATOR.'disable');
    $this->addon = new AddonManager();
});

it('has addon', function (): void {
    expect($this->addon->hasAddon('test'))->toBeTrue()
        ->and($this->addon->hasAddon('test2'))->toBeFalse()
        ->and($this->addon->hasAddon('test1'))->toBeFalse();
});

it('get addon', function (): void {
    expect($this->addon->getAddon('test'))->toBeInstanceOf(AddonConfigManager::class)
        ->and($this->addon->getAddon('test')->getCode())->toEqual('test')
        ->and($this->addon->getAddon('test')->getBasePath())->toEqual('Test');
});

it('get addon installer', function (): void {
    expect(Addon::getAddonInstaller('test'))->toBeInstanceOf(\Addon\Test\Installer::class);
});

it('get addon Providers', function (): void {
    expect($this->addon->getAddon('test')->getProviders())
        ->toEqual([
            'PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestProviderServices',
        ])
    ;
});

it('get addons', function (): void {
    expect($this->addon->getInstalledAddonsCode())->toEqual(['test', 'test2']);
});

it('get addon directives', function (): void {
    $directives = [
        'test' => [
            'lists' => [
                'title' => '列表展示',
                'type' => 'loop',
                'name' => 'lists',
                'class' => 'PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestDirectives',
                'method' => 'handle',
                'cache' => true,
            ],
            'auth' => [
                'title' => '是否访问',
                'type' => 'if',
                'name' => 'auth',
                'class' => 'PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestDirectives',
                'method' => 'auth',
                'cache' => true,
            ],
        ],
    ];
    expect($this->addon->getDirectives())->toEqual($directives);
});

it('get addon path', function (): void {
    $test = __DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test';
    expect($this->addon->getAddonPath('test'))->toEqual($test)
        ->and($this->addon->getResponsePath('test', 'view', 'Response/Views'))->toEqual($test.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views');
});

it('get addon injects', function (): void {
    expect($this->addon->getInject('test'))->toEqual([
        'payment' => [
            [
                'code' => 'wechat_pay',
                'type' => ['jsapi', 'qrcode'],
                'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestPaymentService',
                'title' => '微信支付',
            ],
            [
                'code' => 'alipay',
                'type' => ['web'],
                'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestAlipayService',
                'title' => '支付宝',
            ],
        ],
        'auth' => [
            [
                'code' => 'qq_login',
                'type' => ['pc', 'mobile'],
                'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
                'title' => 'QQ登录',
            ],
        ],
        'notify' => [
            [
                'code' => 'site_notify',
                'type' => ['site', 'template'],
                'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
                'title' => '站内通知',
            ],
        ],
        'storage' => [
            [
                'code' => 'oss_storage',
                'type' => ['oss', 'private'],
                'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
                'title' => 'OSS 存储',
            ],
        ],
    ])->and($this->addon->getInjects('payment'))->toEqual([
        [
            'code' => 'wechat_pay',
            'type' => ['jsapi', 'qrcode'],
            'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestPaymentService',
            'title' => '微信支付',
        ],
        [
            'code' => 'alipay',
            'type' => ['web'],
            'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestAlipayService',
            'title' => '支付宝',
        ],
    ])->and(Addon::getInjectNotify())->toEqual([
        [
            'code' => 'site_notify',
            'type' => ['site', 'template'],
            'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
            'title' => '站内通知',
        ],
    ]);
});

it('get addon hooks', function (): void {
    expect($this->addon->getHook('test'))->toEqual([
        'payment.success' => [
            [
                'event' => 'payment.success',
                'handler' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestDirectives@paymentSuccess',
                'priority' => 10,
            ],
        ],
    ]);
});

it('dispatch addon hooks', function (): void {
    expect(Addon::triggerHook('payment.success', ['order_id' => 1001]))->toEqual([
        [
            'event' => 'payment.success',
            'order_id' => 1001,
        ],
    ]);
});

it('execute addon injects', function (): void {
    $create = Addon::executeInject('payment', 'wechat_pay', [
        'scene' => 'jsapi',
        'order_no' => 'T1001',
        'amount' => 99.9,
    ], 'create');
    $refund = Addon::executeInject('payment', 'wechat_pay', [
        'order_no' => 'T1001',
        'refund_no' => 'R1001',
        'amount' => 20,
    ], 'refund');

    expect($create)->toBeInstanceOf(\PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult::class)
        ->and($create->toArray())->toMatchArray([
            'status' => 'created',
            'scene' => 'jsapi',
            'channel_trade_no' => 'trade-test-1001',
            'payload' => [
                'order_no' => 'T1001',
                'amount' => 99.9,
            ],
        ])->and($refund)->toBeInstanceOf(\PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult::class)
        ->and($refund->toArray())->toMatchArray([
            'order_no' => 'T1001',
            'refund_no' => 'R1001',
            'amount' => 20,
            'status' => 'success',
        ])->and(Addon::executeInject('auth', 'qq_login', [
        'scene' => 'pc',
    ], 'getAuthorizeUrl'))->toEqual([
        'group' => 'auth',
        'action' => 'getAuthorizeUrl',
        'scene' => 'pc',
        'url' => 'https://example.test/oauth',
    ])->and(Addon::executeInject('notify', 'site_notify', [
        'channel' => 'site',
        'message' => 'hello',
    ], 'send'))->toEqual([
        'group' => 'notify',
        'action' => 'send',
        'channel' => 'site',
        'message' => 'hello',
    ])->and(Addon::executeInject('storage', 'oss_storage', [
        'path' => 'uploads/demo.png',
    ], 'upload'))->toEqual([
        'group' => 'storage',
        'action' => 'upload',
        'disk' => 'oss',
        'path' => 'uploads/demo.png',
    ])->and(Addon::executeInject('storage', 'oss_storage', [
        'path' => 'uploads/demo.png',
    ], 'temporaryUrl'))->toEqual([
        'group' => 'storage',
        'action' => 'temporaryUrl',
        'url' => 'https://example.test/temp/uploads/demo.png',
    ])->and(Addon::executeInject('storage', 'oss_storage', [
        'path' => 'uploads/demo.png',
    ], 'exists'))->toBeTrue();
});

it('resolves payment gateways', function (): void {
    $payments = Addon::payments();
    $default = Addon::payment();
    $specified = Addon::payment('test', 'wechat_pay');
    $alipayList = Addon::payments('test');

    expect($payments)->toHaveCount(2)
        ->and($payments[0]['addon_code'])->toEqual('test')
        ->and($default->definition()['addon_code'])->toEqual('test')
        ->and($specified->definition()['addon_code'])->toEqual('test')
        ->and($specified->definition()['code'])->toEqual('wechat_pay')
        ->and($alipayList)->toHaveCount(2);
});

it('calls payment gateway by channel and addon code', function (): void {
    $result = Addon::payment('test', 'wechat_pay')
        ->channel('jsapi')
        ->create([
            'order_no' => 'T2001',
            'amount' => 199,
            'subject' => '支付测试',
            'notify_url' => 'https://example.test/notify',
        ]);

    expect($result)->toBeInstanceOf(\PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult::class)
        ->and($result->toArray())->toMatchArray([
            'status' => 'created',
            'scene' => 'jsapi',
            'payload' => [
                'order_no' => 'T2001',
                'amount' => 199,
            ],
        ]);
});

it('calls another payment implementation in same addon', function (): void {
    $result = Addon::payment('test', 'alipay')->create([
        'order_no' => 'T3001',
        'amount' => 88.8,
        'subject' => '支付宝支付',
        'notify_url' => 'https://example.test/alipay/notify',
    ], 'web');

    expect($result->toArray())->toMatchArray([
        'status' => 'created',
        'scene' => 'web',
        'action' => 'form',
        'channel_trade_no' => 'trade-ali-1001',
    ]);
});

it('rejects unsupported inject actions', function (): void {
    expect(fn () => Addon::executeInject('storage', 'oss_storage', [
        'path' => 'uploads/demo.png',
    ], 'chat'))->toThrow(AddonException::class, __('ptadmin-addon::messages.definition.inject_action_unsupported', [
        'target' => 'storage:oss_storage',
        'method' => 'chat',
    ]));
});

it('disable and enable addon', function (): void {
    $testAddonDir = __DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test';
    $fakeService = new FakeAdminResourceServiceForAddonTest();
    app()->instance('PTAdmin\Contracts\Auth\AdminResourceServiceInterface', $fakeService);

    AddonAction::disable('test');

    expect(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'disable'))->toBeTrue()
        ->and(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'disable.log'))->toBeTrue()
        ->and(Addon::hasAddon('test'))->toBeFalse();

    AddonAction::enable('test');

    expect(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'disable'))->toBeFalse()
        ->and(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'enable.log'))->toBeTrue()
        ->and(Addon::hasAddon('test'))->toBeTrue()
        ->and($fakeService->disabled)->toEqual(['test'])
        ->and($fakeService->synced)->toHaveCount(1)
        ->and(data_get($fakeService->synced[0], 'addon_code'))->toEqual('test')
        ->and(data_get($fakeService->synced[0], 'definitions.0.code'))->toEqual('test')
        ->and(data_get($fakeService->synced[0], 'definitions.1.code'))->toEqual('test.dashboard')
        ->and(data_get($fakeService->synced[0], 'definitions.2.code'))->toEqual('test.dashboard.create')
        ->and(data_get($fakeService->synced[0], 'definitions.2.type'))->toEqual('btn');
});

it('enable disabled addon without bootstrap', function (): void {
    $testAddonDir = __DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2';

    expect(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'disable'))->toBeTrue()
        ->and(Addon::hasAddon('test2'))->toBeFalse();

    AddonAction::enable('test2');

    expect(file_exists($testAddonDir.\DIRECTORY_SEPARATOR.'disable'))->toBeFalse()
        ->and(Addon::hasAddon('test2'))->toBeTrue();
});

it('upgrade addon from downloaded package', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-upgrade-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['version'] = 'v0.0.2';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'upgrade.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);
    $zipBody = file_get_contents($zipFile);

    Cache::put('ptadmin:addon_user_keys', serialize(['token' => 'Bearer test-token']));
    $postResponse = new class
    {
        public function status(): int
        {
            return 200;
        }

        public function json($key = null)
        {
            $data = [
                'code' => 0,
                'data' => [
                    'url' => 'https://example.com/test-upgrade.zip',
                    'hash' => md5((string) test()->zipBody),
                ],
            ];

            return null === $key ? $data : data_get($data, $key);
        }

        public function body(): string
        {
            return (string) json_encode($this->json(), JSON_UNESCAPED_UNICODE);
        }
    };
    $getResponse = new class
    {
        public function successful(): bool
        {
            return true;
        }

        public function body(): string
        {
            return (string) test()->zipBody;
        }
    };
    $this->zipBody = $zipBody;

    Http::shouldReceive('withHeaders')->once()->andReturnSelf();
    Http::shouldReceive('withToken')->once()->with('test-token')->andReturnSelf();
    Http::shouldReceive('withOptions')->twice()->andReturnSelf();
    Http::shouldReceive('post')->once()->withArgs(function (string $url): bool {
        return 'https://www.pangtou.com/api-addon/download' === $url;
    })->andReturn($postResponse);
    Http::shouldReceive('get')->once()->with('https://example.com/test-upgrade.zip')->andReturn($getResponse);

    AddonAction::upgrade('test', 0, true);

    expect(Addon::getAddonVersion('test'))->toEqual('v0.0.2')
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'upgrade.log'))->toBeTrue();

    $filesystem->deleteDirectory($basePath);
});

it('prevent upgrade when addon is in develop mode without force', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-develop-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    $configFile = $basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['develop'] = true;
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    Http::shouldReceive('withHeaders')->never();
    Http::shouldReceive('withOptions')->never();

    expect(AddonAction::upgrade('test'))->toBeNull()
        ->and(Addon::getAddonVersion('test'))->toEqual('v0.0.1')
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'upgrade.log'))->toBeFalse();

    $filesystem->deleteDirectory($basePath);
});

it('install addon from local zip package', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-local-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test');
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2');
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'local-install.zip';
    buildAddonPackageZip(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();
    $fakeService = new FakeAdminResourceServiceForAddonTest();
    app()->instance('PTAdmin\Contracts\Auth\AdminResourceServiceInterface', $fakeService);

    expect(Addon::hasAddon('test'))->toBeFalse();

    AddonAction::installLocal($zipFile);

    expect(Addon::hasAddon('test'))->toBeTrue()
        ->and(Addon::getAddonVersion('test'))->toEqual('v0.0.1')
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'install.log'))->toBeTrue()
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'init.log'))->toBeTrue()
        ->and($fakeService->synced)->toHaveCount(1)
        ->and(data_get($fakeService->synced[0], 'addon_code'))->toEqual('test')
        ->and(data_get($fakeService->synced[0], 'definitions.0.code'))->toEqual('test')
        ->and(data_get($fakeService->synced[0], 'definitions.1.code'))->toEqual('test.dashboard')
        ->and(data_get($fakeService->synced[0], 'definitions.2.code'))->toEqual('test.dashboard.create');

    $filesystem->deleteDirectory($basePath);
});

it('prevent local install when php compatibility is not satisfied', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-php-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test');
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2');
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['compatibility']['php'] = '>=99.0.0';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'php-compat.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    expect(fn () => AddonAction::installLocal($zipFile))
        ->toThrow(AddonException::class, __('ptadmin-addon::messages.validator.php_constraint_failed', [
            'code' => 'test',
            'constraint' => '>=99.0.0',
        ]));

    $filesystem->deleteDirectory($basePath);
});

it('prevent local install when configured host version is not satisfied', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-host-version-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test');
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2');
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['compatibility']['ptadmin/admin'] = '>=2.0.0';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'host-version.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app['config']->set('addon.host_versions', [
        'ptadmin/admin' => '1.0.0',
    ]);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    expect(fn () => AddonAction::installLocal($zipFile))
        ->toThrow(AddonException::class, __('ptadmin-addon::messages.validator.host_constraint_failed', [
            'code' => 'test',
            'target' => 'ptadmin/admin',
            'constraint' => '>=2.0.0',
            'version' => '1.0.0',
        ]));

    $filesystem->deleteDirectory($basePath);
});

it('prevent local install when dependency addon is missing', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-dependency-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test');
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2');
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['dependencies']['plugins'] = ['missing-addon'];
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'dependency.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    expect(fn () => AddonAction::installLocal($zipFile))
        ->toThrow(AddonException::class, __('ptadmin-addon::messages.validator.dependency_missing', [
            'code' => 'test',
            'target' => 'missing-addon',
        ]));

    $filesystem->deleteDirectory($basePath);
});

it('prevent local install when official addon is not purchased', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-purchase-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test');
    $filesystem->deleteDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test2');
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['marketplace'] = [
        'official' => true,
        'product_id' => 'test-product',
    ];
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'purchase.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    Cache::put('ptadmin:addon_user_keys', serialize(['token' => 'Bearer test-token']));

    $existsResponse = new class
    {
        public function status(): int
        {
            return 200;
        }

        public function json($key = null)
        {
            $data = [
                'code' => 0,
                'data' => [
                    'is_exists' => true,
                ],
            ];

            return null === $key ? $data : data_get($data, $key);
        }

        public function body(): string
        {
            return (string) json_encode($this->json(), JSON_UNESCAPED_UNICODE);
        }
    };

    $verifyResponse = new class
    {
        public function status(): int
        {
            return 200;
        }

        public function json($key = null)
        {
            $data = [
                'code' => 0,
                'data' => [
                    'purchased' => false,
                    'buy_url' => 'https://www.pangtou.com/buy/test-product',
                ],
            ];

            return null === $key ? $data : data_get($data, $key);
        }

        public function body(): string
        {
            return (string) json_encode($this->json(), JSON_UNESCAPED_UNICODE);
        }
    };

    Http::shouldReceive('withHeaders')->twice()->andReturnSelf();
    Http::shouldReceive('withToken')->twice()->with('test-token')->andReturnSelf();
    Http::shouldReceive('withOptions')->twice()->andReturnSelf();
    Http::shouldReceive('post')->once()->withArgs(function (string $url): bool {
        return 'https://www.pangtou.com/api-addon/addon-exists/test' === $url;
    })->andReturn($existsResponse);
    Http::shouldReceive('post')->once()->withArgs(function (string $url): bool {
        return 'https://www.pangtou.com/api-addon/verify' === $url;
    })->andReturn($verifyResponse);

    expect(fn () => AddonAction::installLocal($zipFile))
        ->toThrow(AddonException::class, __('ptadmin-addon::messages.validator.purchase_required', [
            'code' => 'test',
        ]));

    $filesystem->deleteDirectory($basePath);
});

it('rollback addon files when local install lifecycle fails', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-rollback-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');

    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['version'] = 'v9.9.9';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    file_put_contents($packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'install.sql', 'THIS IS INVALID SQL;');

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'rollback.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    expect(fn () => AddonAction::installLocal($zipFile, true))
        ->toThrow(AddonException::class);

    Addon::clearResolvedInstance('addon');
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });

    expect(Addon::getAddonVersion('test'))->toEqual('v0.0.1')
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json'))->toBeTrue();

    $filesystem->deleteDirectory($basePath);
});

it('force overwrite local installed addon', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-force-local-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);

    $packageDir = $basePath.\DIRECTORY_SEPARATOR.'package-source';
    $filesystem->copyDirectory($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test', $packageDir.\DIRECTORY_SEPARATOR.'Test');
    $configFile = $packageDir.\DIRECTORY_SEPARATOR.'Test'.\DIRECTORY_SEPARATOR.'manifest.json';
    $config = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    $config['version'] = 'v0.0.2';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $zipFile = $basePath.\DIRECTORY_SEPARATOR.'force-local.zip';
    buildAddonPackageZip($packageDir.\DIRECTORY_SEPARATOR.'Test', $zipFile);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();

    AddonAction::installLocal($zipFile, true);

    expect(Addon::getAddonVersion('test'))->toEqual('v0.0.2');

    $filesystem->deleteDirectory($basePath);
});

it('run uninstall lifecycle when removing addon', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-uninstall-'.uniqid();
    $filesystem->copyDirectory(__DIR__.\DIRECTORY_SEPARATOR.'testSrc', $basePath);

    $this->app->setBasePath($basePath);
    $this->app->forgetInstance('addon');
    $this->app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();
    $fakeService = new FakeAdminResourceServiceForAddonTest();
    app()->instance('PTAdmin\Contracts\Auth\AdminResourceServiceInterface', $fakeService);

    AddonAction::uninstall('test', true);

    expect(file_exists($basePath.\DIRECTORY_SEPARATOR.'addon-uninstall.log'))->toBeTrue()
        ->and(is_dir($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'Test'))->toBeFalse()
        ->and($fakeService->deleted)->toEqual(['test']);

    $filesystem->deleteDirectory($basePath);
});

it('init addon scaffold with standard development structure', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-init-'.uniqid();
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $this->app->setBasePath($basePath);

    expect(Artisan::call('addon:init', [
        'code' => 'demo-addon',
        '--title' => 'Demo Addon',
    ]))->toEqual(0);

    $addonDir = $basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'DemoAddon';
    $manifestFile = $addonDir.\DIRECTORY_SEPARATOR.'manifest.json';
    $manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);

    expect(is_dir($addonDir))->toBeTrue()
        ->and(file_exists($manifestFile))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Installer.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Bootstrap.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'README.md'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'functions.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Dashboard'.\DIRECTORY_SEPARATOR.'DemoAddonOverviewWidget.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Providers'.\DIRECTORY_SEPARATOR.'DemoAddonServiceProvider.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Models'.\DIRECTORY_SEPARATOR.'DemoAddon.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Service'.\DIRECTORY_SEPARATOR.'DemoAddonService.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Admin'.\DIRECTORY_SEPARATOR.'DemoAddonController.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Api'.\DIRECTORY_SEPARATOR.'DemoAddonController.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Home'.\DIRECTORY_SEPARATOR.'DemoAddonController.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Requests'.\DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Migrations'.\DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Seeders'.\DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'admin.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'api.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'web.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Config'.\DIRECTORY_SEPARATOR.'config.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'home'.\DIRECTORY_SEPARATOR.'index.blade.php'))->toBeTrue()
        ->and(file_exists($addonDir.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'ptadmin'.\DIRECTORY_SEPARATOR.'index.blade.php'))->toBeTrue()
        ->and($manifest['code'])->toEqual('demo-addon')
        ->and($manifest['develop'])->toBeTrue()
        ->and(data_get($manifest, 'compatibility.php'))->toEqual('>=7.4')
        ->and(data_get($manifest, 'providers.0'))->toEqual('Addon\\DemoAddon\\Providers\\DemoAddonServiceProvider')
        ->and(data_get($manifest, 'entry.installer'))->toEqual('Addon\\DemoAddon\\Installer')
        ->and(data_get($manifest, 'entry.bootstrap'))->toEqual('Addon\\DemoAddon\\Bootstrap');

    $config = include $addonDir.\DIRECTORY_SEPARATOR.'Config'.\DIRECTORY_SEPARATOR.'config.php';

    expect($config['code'])->toEqual('demo-addon')
        ->and($config['name'])->toEqual('Demo Addon')
        ->and($config['admin_route_prefix'])->toEqual('demo-addon')
        ->and($config['api_route_prefix'])->toEqual('api/demo-addon');

    $filesystem->deleteDirectory($basePath);
});

it('force recreate addon scaffold', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ptadmin-addon-init-force-'.uniqid();
    $filesystem->ensureDirectoryExists($basePath.\DIRECTORY_SEPARATOR.'addons');

    $this->app->setBasePath($basePath);

    Artisan::call('addon:init', [
        'code' => 'demo-addon',
        '--title' => 'Demo Addon',
    ]);
    file_put_contents($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'DemoAddon'.\DIRECTORY_SEPARATOR.'custom.txt', 'custom');

    expect(Artisan::call('addon:init', [
        'code' => 'demo-addon',
        '--title' => 'Forced Demo',
        '--force' => true,
    ]))->toEqual(0);

    $manifestFile = $basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'DemoAddon'.\DIRECTORY_SEPARATOR.'manifest.json';
    $manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toEqual('Forced Demo')
        ->and(file_exists($basePath.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR.'DemoAddon'.\DIRECTORY_SEPARATOR.'custom.txt'))->toBeFalse();

    $filesystem->deleteDirectory($basePath);
});

function buildAddonPackageZip(string $sourceDir, string $zipFilename): void
{
    $zip = new ZipArchive();
    $zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $root = basename($sourceDir);
    $zip->addEmptyDir($root);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $localPath = $root.\DIRECTORY_SEPARATOR.$iterator->getSubPathName();
        if ($file->isDir()) {
            $zip->addEmptyDir($localPath);

            continue;
        }
        $zip->addFile($file->getPathname(), $localPath);
    }

    $zip->close();
}
