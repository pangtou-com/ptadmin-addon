<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
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

namespace PTAdmin\Addon\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Commands\AddonCache;
use PTAdmin\Addon\Commands\AddonCacheClear;
use PTAdmin\Addon\Commands\AddonInstall;
use PTAdmin\Addon\Commands\AddonLogin;
use PTAdmin\Addon\Commands\AddonUninstall;
use PTAdmin\Addon\Commands\AddonUpgrade;
use PTAdmin\Addon\Commands\AddonUpload;
use PTAdmin\Addon\Compiler\PTCompiler;
use PTAdmin\Addon\Controller\InstallController;
use PTAdmin\Addon\Middleware\AddonMiddleware;
use PTAdmin\Addon\Middleware\CanInstallMiddleware;
use PTAdmin\Addon\Service\AddonManager;

class AddonServiceProvider extends ServiceProvider
{
    private $addon_booting = [];

    public function register(): void
    {
        $this->app->singleton('addon', function () {
            return new AddonManager();
        });
        $this->app->singleton('__addon__', function () {
            return new AddonMiddleware();
        });
        $this->app->singleton('install', function () {
            return new CanInstallMiddleware();
        });
        $this->registerProvider($this->app);
    }

    public function boot(): void
    {
        Route::pattern('id', '[1-9][0-9]*');
        $this->registerCompiler();
        $this->commands([
            AddonInstall::class,
            AddonUninstall::class,
            AddonUpgrade::class,
            AddonUpload::class,
            AddonCache::class,
            AddonCacheClear::class,
            AddonLogin::class,
        ]);
        foreach ($this->addon_booting as $addonCode) {
            $this->registerLang($addonCode);
            $this->registerViews($addonCode);
            $this->registerConfig($addonCode);
            $this->registerHelper($addonCode);
            $this->registerRoutes($addonCode);
        }
        if (!file_exists(storage_path('installed'))) {
            $this->registerInstaller();
        }
    }

    /**
     * 注册视图文件.
     *
     * @param $addonCode
     */
    protected function registerViews($addonCode): void
    {
        $path = Addon::getResponsePath($addonCode, 'view', 'Response/Views');
        if (is_dir($path)) {
            $this->loadViewsFrom($path, $addonCode);
        }
    }

    /**
     * 注册配置文件.
     *
     * @param $addonCode
     */
    protected function registerConfig($addonCode): void
    {
        $path = Addon::getResponsePath($addonCode, 'config', 'Config/config.php');
        if (is_file($path) && file_exists($path)) {
            $this->mergeConfigFrom($path, $addonCode);
        }
    }

    /**
     * 注册语言包文件.
     *
     * @param mixed $addonCode
     */
    protected function registerLang($addonCode): void
    {
        $path = Addon::getResponsePath($addonCode, 'lang', 'Response/Lang');
        if (is_dir($path)) {
            $this->loadTranslationsFrom($path, $addonCode);
        }
    }

    /**
     * Register routes.
     * 注册路由.
     *
     * @param mixed $addonCode
     */
    protected function registerRoutes($addonCode): void
    {
        $routesDir = Addon::getResponsePath($addonCode, 'route', 'Routes');
        $routes = [];
        if (is_dir($routesDir)) {
            $routes = array_diff(scandir($routesDir), ['.', '..', '.gitkeep']);
        }
        $addonBasePath = Addon::getAddon($addonCode)['base_path'];
        foreach ($routes as $route) {
            if (!Str::endsWith($route, '.php')) {
                continue;
            }
            $middleware = ["__addon__:{$addonCode},{$addonBasePath}"];
            Route::middleware($middleware)->group($routesDir.\DIRECTORY_SEPARATOR.$route);
        }
    }

    /**
     * 加载助手函数.
     *
     * @param mixed $addonCode
     */
    protected function registerHelper($addonCode): void
    {
        $path = Addon::getResponsePath($addonCode, 'func', 'functions.php');
        if (is_file($path) && file_exists($path)) {
            include_once $path;
        }
    }

    /**
     * 注册插件应用的服务提供者.
     *
     * @param $app
     */
    private function registerProvider($app): void
    {
        $providers = Addon::getProviders();
        foreach ($providers as $key => $item) {
            $item = Arr::wrap($item);
            foreach ($item as $val) {
                $provider = $app->register($val);
                // 兼容自定义服务提供者
                if (method_exists($provider, 'boot')) {
                    $this->addon_booting[] = $key;
                }
            }
        }
    }

    private function registerInstaller(): void
    {
        $this->mergeConfigFrom($this->getPath('Config/install.php'), 'install');
        $this->loadViewsFrom($this->getPath('Response/Views'), 'install');
        Route::group(['prefix' => 'install'], function (): void {
            Route::get('/', [InstallController::class, 'welcome']);
            Route::get('/requirements', [InstallController::class, 'requirements']);
            Route::match(['get', 'post'], '/env', [InstallController::class, 'environment']);
            Route::match(['post'], '/stream', [InstallController::class, 'stream']);
        });
    }

    private function getPath(string $path): string
    {
        $dir = \dirname(__DIR__);

        return $dir.\DIRECTORY_SEPARATOR.$path;
    }

    /**
     * 注册自定义编译器.
     */
    private function registerCompiler(): void
    {
        $old = app('blade.compiler');
        $this->app->singleton('blade.compiler', function ($app) use ($old) {
            $compiler = new PTCompiler($app['files'], $app['config']['view.compiled']);
            $compiler->cloneBaseCompiler($old);
            Blade::swap($compiler);

            return $compiler;
        });
    }
}
