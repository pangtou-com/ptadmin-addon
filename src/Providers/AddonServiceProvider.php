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

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use PTAdmin\Addon\Commands\AddonCache;
use PTAdmin\Addon\Commands\AddonCacheClear;
use PTAdmin\Addon\Commands\AddonInstall;
use PTAdmin\Addon\Commands\AddonPack;
use PTAdmin\Addon\Commands\AddonUninstall;
use PTAdmin\Addon\Commands\AddonUpdate;
use PTAdmin\Addon\Commands\AddonUpload;
use PTAdmin\Addon\Compiler\PTCompiler;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Middleware\AddonMiddleware;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\BootstrapManage;

class AddonServiceProvider extends ServiceProvider
{
    private $addon_booting;
    public function register(): void
    {
        $addon = AddonManager::getInstance();
        $addon->boot();
        $this->app->singleton("addon", function () use ($addon) {
            return $addon;
        });
        $this->app->singleton("__addon__", function () {
            return new AddonMiddleware();
        });

        $this->addon_booting = BootstrapManage::registerProvider($this->app);
    }

    public function boot(): void
    {
        $this->registerCompiler();
        $this->commands([
            AddonInstall::class,
            AddonUninstall::class,
            AddonUpdate::class,
            AddonPack::class,
            AddonUpload::class,
            AddonCache::class,
            AddonCacheClear::class,
        ]);
        if ($this->addon_booting && is_array($this->addon_booting)) {
            foreach ($this->addon_booting as $addonCode) {
                $this->registerLang($addonCode);
                $this->registerViews($addonCode);
                $this->registerConfig($addonCode);
                $this->registerHelper($addonCode);
                $this->registerRoutes($addonCode);
            }
        }
    }

    /**
     * 注册视图文件
     * @param $addonCode
     * @return void
     */
    protected function registerViews($addonCode): void
    {
        $instance = AddonManager::getInstance();
        $response = $instance->getResponse($addonCode);
        $path = $instance->getAddonPath($addonCode, data_get($response, "view", "Response/Views"));
        if (is_dir($path)) {
            $this->loadViewsFrom($path, $addonCode);
        }
    }

    /**
     * 注册配置文件
     * @param $addonCode
     * @return void
     */
    protected function registerConfig($addonCode): void
    {
        $instance = AddonManager::getInstance();
        $response = $instance->getResponse($addonCode);
        $path = $instance->getAddonPath($addonCode, data_get($response, "config", "Config/config.php"));
        if (is_file($path) && file_exists($path)) {
            $this->mergeConfigFrom($path, $addonCode);
        }
    }

    /**
     * 注册语言包文件.
     */
    protected function registerLang($addonCode): void
    {
        $instance = AddonManager::getInstance();
        $response = $instance->getResponse($addonCode);
        $path = $instance->getAddonPath($addonCode, data_get($response, "lang", "Response/Lang"));
        if (is_dir($path)) {
            $this->loadTranslationsFrom($path, $addonCode);
        }
    }

    /**
     * Register routes.
     * 注册路由.
     * 自动扫描插件Routes目录下的路由文件.并将api为前缀的文件设置为api中间件.
     */
    protected function registerRoutes($addonCode): void
    {
        $instance = AddonManager::getInstance();
        $response = $instance->getResponse($addonCode);
        $routesDir = $instance->getAddonPath($addonCode, data_get($response, "route", "Routes"));
        $routes = [];
        if (is_dir($routesDir)) {
            $routes = array_diff(scandir($routesDir), ['.', '..', '.gitkeep']);
        }
        $addonBasePath = $instance->getAddon($addonCode)['base_path'];
        foreach ($routes as $route) {
            if (!Str::endsWith($route, '.php')) {
                continue;
            }
            $middleware = ["__addon__:{$addonCode},{$addonBasePath}"];
            $middleware[] = Str::startsWith($route, 'api') ? 'api' : 'web';
            Route::middleware($middleware)->group($routesDir.\DIRECTORY_SEPARATOR.$route);
        }
    }

    /**
     * 加载助手函数.
     */
    protected function registerHelper($addonCode): void
    {
        $instance = AddonManager::getInstance();
        $response = $instance->getResponse($addonCode);
        $path = $instance->getAddonPath($addonCode, data_get($response, "func", "functions.php"));
        if (is_file($path) && file_exists($path)) {
            include_once $path;
        }
    }

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
