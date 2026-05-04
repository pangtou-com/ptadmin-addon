<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Tests\Feature\Package;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Providers\AddonServiceProvider;
use PTAdmin\AddonTests\TestCase;

class AddonServiceProviderViewOverrideTest extends TestCase
{
    private string $addonRoot;
    private string $overrideRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addonRoot = base_path('addons'.\DIRECTORY_SEPARATOR.'Test');
        $this->overrideRoot = resource_path('views'.\DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'test');

        File::ensureDirectoryExists($this->addonRoot.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'home');
        File::ensureDirectoryExists($this->overrideRoot.\DIRECTORY_SEPARATOR.'home');

        File::put($this->addonRoot.\DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'id' => 'test',
            'code' => 'test',
            'name' => '测试插件',
            'version' => '1.0.0',
            'providers' => [],
            'resources' => [
                'assets' => './Response/Views',
                'routes' => './Response/Views',
                'views' => './Response/Views',
                'lang' => './Response/Views',
                'config' => './Response/Views',
                'functions' => './Response/Views',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        File::put(
            $this->addonRoot.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'home'.\DIRECTORY_SEPARATOR.'demo.blade.php',
            'addon-view'
        );
        File::put(
            $this->overrideRoot.\DIRECTORY_SEPARATOR.'home'.\DIRECTORY_SEPARATOR.'demo.blade.php',
            'override-view'
        );

        $this->app->forgetInstance('addon');
        $this->app->singleton('addon', function (): AddonManager {
            return new AddonManager();
        });
        Addon::clearResolvedInstance('addon');

        $provider = new AddonServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'registerViews');
        $reflection->setAccessible(true);
        $reflection->invoke($provider, 'test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('addons'.\DIRECTORY_SEPARATOR.'Test'));
        File::deleteDirectory(resource_path('views'.\DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'test'));

        parent::tearDown();
    }

    public function test_host_view_override_path_takes_priority_over_addon_default_view(): void
    {
        $this->assertSame('override-view', trim((string) view('test::home.demo')->render()));
    }

    public function test_it_registers_view_publish_path_for_addon_override_directory(): void
    {
        $viewPublishes = ServiceProvider::pathsToPublish(AddonServiceProvider::class, 'ptadmin-view');

        $this->assertCount(1, $viewPublishes);
        $this->assertSame($this->addonRoot.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views', array_key_first($viewPublishes));
        $this->assertSame($this->overrideRoot, array_values($viewPublishes)[0]);
    }
}
