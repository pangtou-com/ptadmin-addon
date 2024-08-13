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

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class BaseAddonService extends ServiceProvider
{
    // 插件名称，用于标识插件，必须唯一
    protected $addonName = '';

    // 插件路径, 一般情况下使用插件名称作为路径，首字母大写
    protected $addonPath = '';

    /**
     * @var mixed 插件暴露到模版指令中的方法
     *
     * @see https://docs.pangtou.com/ptadmin/2.0/develop/addon.html
     */
    protected $export;

    /**
     * 服务注册，系统启动时会自动调用，注意只能注册不依赖于其他服务的服务
     */
    public function register(): void
    {
    }

    /**
     * 服务启动，系统启动时会自动调用。可以在这里注册路由、监听事件、注册中间件等.
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerRoutes();
        $this->registerConfig();
        $this->registerLang();
        $this->registerHelper();
    }

    public function getAddonName(): string
    {
        return $this->addonName;
    }

    public function getAddonPath($path = ''): string
    {
        if (!$this->addonPath) {
            $addon = explode('\\', static::class);
            $this->addonPath = $addon[1];
        }

        return base_path('addons'.\DIRECTORY_SEPARATOR.$this->addonPath.('' === $path ? '' : \DIRECTORY_SEPARATOR.$path));
    }

    public function getExport()
    {
        return $this->export;
    }

    /**
     * Register views.
     * 注册视图.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom($this->getAddonPath('Response/Views'), $this->getAddonName());
    }

    /**
     * 注册配置文件.
     */
    protected function registerConfig(): void
    {
        $path = $this->getAddonPath('Config/config.php');
        if (!file_exists($path)) {
            return;
        }
        $this->mergeConfigFrom($path, $this->getAddonName());
    }

    /**
     * 注册语言包文件.
     */
    protected function registerLang(): void
    {
        $path = $this->getAddonPath('Response/Lang');
        if (!is_dir($path)) {
            return;
        }
        $this->loadTranslationsFrom($path, $this->getAddonName());
    }

    /**
     * Register routes.
     * 注册路由.
     * 自动扫描插件Routes目录下的路由文件.并将api为前缀的文件设置为api中间件.
     */
    protected function registerRoutes(): void
    {
        $routesDir = $this->getAddonPath('Routes');
        $routes = [];
        if (is_dir($routesDir)) {
            $routes = array_diff(scandir($routesDir), ['.', '..', '.gitkeep']);
        }
        foreach ($routes as $route) {
            if (!Str::endsWith($route, '.php')) {
                continue;
            }
            $middleware = ["addon:{$this->getAddonName()},{$this->addonPath}"];
            $middleware[] = Str::startsWith($route, 'api') ? 'api' : 'web';
            Route::middleware($middleware)->group($this->getAddonPath('Routes/'.$route));
        }
    }

    /**
     * 加载助手函数.
     */
    protected function registerHelper(): void
    {
        $path = $this->getAddonPath('functions.php');
        if (file_exists($path)) {
            include_once $this->getAddonPath('functions.php');
        }
    }
}
