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

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件管理.
 */
class AddonManager
{

    /** @var AddonConfigManager 插件配置管理器对象 */
    protected $addonManager;

    public function __construct()
    {
        $this->addonManager = new AddonConfigManager();
    }


    public function getAddonManager(): AddonConfigManager
    {
        return $this->addonManager;
    }

    /**
     * 启动插件项目
     * @return void
     */
    public function boot()
    {
        if ($this->addonManager->getLoadStatus()) {
            return;
        }
        if ((boolean)config("app.debug") === true && file_exists($this->getAddonCacheDir())) {
            $data = require_once $this->getAddonCacheDir();
            $this->addonManager->byCacheLoadConfig($data, $this);
            return;
        }

        $this->addonManager->loadConfig($this->getAddonsDirs(), $this);
    }

    /**
     * 插件是否禁用中
     * @param $addonDir
     * @return bool
     */
    public function isAddonDisable($addonDir): bool
    {
        return file_exists($addonDir.\DIRECTORY_SEPARATOR.'disable');
    }

    /**
     * 获取缓存文件路径
     * @return string
     */
    protected function getAddonCacheDir(): string
    {
        return base_path('bootstrap'.\DIRECTORY_SEPARATOR.'cache'.\DIRECTORY_SEPARATOR.'addons.php');
    }


    /**
     * 扫描所有的插件完整目录
     * @return array
     */
    protected function getAddonsDirs(): array
    {
        $addons = [];
        $dirs = $this->scanAddonsPath();
        foreach ($dirs as $dir) {
            $addon_path = base_path("addons".\DIRECTORY_SEPARATOR.$dir);
            if (!is_dir($addon_path)) {
                continue;
            }
            $addons[] = $addon_path;
        }
        return $addons;
    }

    /**
     * 执行指令方法.
     *
     * @param $addonCode
     * @param $method
     * @param array $params
     *
     * @return null|mixed
     */
    public function execute($addonCode, $method, array $params = [])
    {
        if (!$this->hasAddon($addonCode)) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        return AddonDirectivesActuator::handle($addonCode, $method, DirectivesDTO::build($params));
    }

    /**
     * 判断插件是否存在.
     *
     * @param $addonCode
     *
     * @return bool
     */
    public function hasAddon($addonCode): bool
    {
        $addons = $this->getAddon($addonCode);

        return $addons !== null;
    }

    public function getProviders(): array
    {
        return $this->getAddonManager()->getProviders();
    }

    public function getProvider($addonCode)
    {
        return  $this->getAddonManager()->getProviders($addonCode);
    }

    public function getInjects(): array
    {
        return $this->getAddonManager()->getInject();
    }

    public function getInject($addonCode)
    {
        return $this->getAddonManager()->getInject($addonCode);
    }

    public function getResponses(): array
    {
        return $this->getAddonManager()->getResponse();
    }

    public function getResponse($addonCode)
    {
        return $this->getAddonManager()->getResponse($addonCode);
    }

    public function getDirectives(): array
    {
        return $this->getAddonManager()->getDirectives();
    }

    public function getDirective($addonCode)
    {
        return $this->getAddonManager()->getDirectives($addonCode);
    }

    public function getAddons(): array
    {
        return $this->getAddonManager()->getAddons();
    }

    public function getAddon($addonCode, $key = null, $default = null)
    {
        $addon = $this->getAddonManager()->getAddons($addonCode);
        if ($addon === null) {
            return $default;
        }
        if (null === $key) {
            return $addon;
        }

        return $addon[$key] ?? $default;
    }

    public function getAddonPath($addonCode, $path = null): string
    {
        $addon = $this->getAddon($addonCode);
        if ($addon === null) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        return base_path('addons'.\DIRECTORY_SEPARATOR.$addon['base_path'].(null !== $path ? \DIRECTORY_SEPARATOR.$path : ''));
    }

    /**
     * 校验插件版本.
     *
     * @param $addonCode
     * @param $version
     *
     * @return bool
     */
    public function checkAddonVersion($addonCode, $version): bool
    {
        if (!$this->hasAddon($addonCode)) {
            return false;
        }
        $var = $this->getAddonVersion($addonCode);
        if (null === $var) {
            return false;
        }

        return version_if($var, $version);
    }

    /**
     * 扫描插件目录
     * @return array
     */
    protected function scanAddonsPath(): array
    {
        $dirs = array_diff(scandir(base_path('addons')), ['.', '..', '.gitkeep', '.gitignore']);
        if (0 === \count($dirs)) {
            return [];
        }

        return $dirs;
    }

    /**
     * 获取所有的已安装插件code.
     *
     * @return array
     */
    public function getInstalledAddonsCode(): array
    {
        return array_keys($this->getInstalledAddons());
    }

    /**
     * 本地已安装插件信息.
     *
     * @return array
     */
    public function getInstalledAddons(): array
    {
        $addons = $this->getAddonsDirs();
        $results = [];
        foreach ($addons as $addon) {
            $config = $this->getAddonManager()->readAddonConfig($addon);
            if ($config === null) {
                continue;
            }
            $config['disable'] = $this->isAddonDisable($addon);
            $results[$config['code']] = $config;
        }

        return $results;
    }

    /**
     * 获取插件依赖.
     *
     * @param $addonCode
     *
     * @return array
     */
    public function getAddonRequired($addonCode): array
    {
        $addon = $this->getAddon($addonCode);

        return $addon['require'] ?? [];
    }

    /**
     * 判断当前插件是否属于必须插件.
     *
     * @param $addonCode
     *
     * @return bool
     */
    public function hasAddonRequired($addonCode): bool
    {
        $addons = $this->getAddons();
        foreach ($addons as $value) {
            if (isset($value['require'], $value['require'][$addonCode])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断插件依赖是否满足.
     *
     * @param $addonCode
     *
     * @return bool
     */
    public function addonRequired($addonCode): bool
    {
        $required = $this->getAddonRequired($addonCode);
        if (\count($required) < 1) {
            return true;
        }
        foreach ($required as $key => $val) {
            if (!$this->checkAddonVersion($key, $val)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取插件版本.
     *
     * @param $addonCode
     *
     * @return null|string
     */
    public function getAddonVersion($addonCode): ?string
    {
        $addon = $this->getAddon($addonCode);

        return $addon['version'] ?? null;
    }

    /**
     * 获取资源路径
     * @param $addonCode
     * @param $key
     * @param $default
     * @return string
     */
    public function getResponsePath($addonCode, $key, $default = null): string
    {
        return $this->getAddonPath($addonCode, data_get($this->getResponse($addonCode), $key, $default));
    }
}
