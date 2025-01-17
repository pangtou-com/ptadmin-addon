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

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件管理.
 */
final class AddonManager
{
    /** @var AddonConfigManager[] 插件配置管理器对象 */
    private $addonManager = [];

    public function __construct()
    {
        $this->initialize();
    }

    public function __toString()
    {
        return var_export($this->toArray(), true);
    }

    /**
     * 插件是否禁用中.
     *
     * @param string $addonDir 插件目录
     *
     * @return bool
     */
    public function isAddonDisable(string $addonDir): bool
    {
        return file_exists($addonDir.\DIRECTORY_SEPARATOR.'disable');
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
        return isset($this->addonManager[$addonCode]);
    }

    public function getProviders(): array
    {
        return data_get($this->toArray(), '*.providers', []);
    }

    public function getProvider($addonCode): array
    {
        return $this->getAddonManager($addonCode)->getProviders();
    }

    public function getInjects(): array
    {
        return data_get($this->toArray(), '*.injects', []);
    }

    public function getInject($addonCode): array
    {
        return $this->getAddonManager($addonCode)->getInject();
    }

    public function getResponses(): array
    {
        return data_get($this->toArray(), '*.response', []);
    }

    public function getResponse($addonCode): array
    {
        return $this->getAddonManager($addonCode)->getResponse();
    }

    public function getDirectives(): array
    {
        $data = [];
        foreach ($this->addonManager as $key => $value) {
            $data[$key] = $value->getDirectives();
        }

        return $data;
    }

    public function getDirective($addonCode): array
    {
        return $this->getAddonManager($addonCode)->getDirectives();
    }

    public function getAddons(): array
    {
        return $this->toArray();
    }

    public function getAddon($addonCode): AddonConfigManager
    {
        return $this->getAddonManager($addonCode);
    }

    public function getAddonPath($addonCode, $path = null): string
    {
        return $this->getAddon($addonCode)->getAddonPath($path);
    }

    /**
     * 获取插件命名空间.
     *
     * @param $addonCode
     * @param $namespace
     *
     * @return string
     */
    public function getAddonNamespace($addonCode, $namespace = null): string
    {
        return $this->getAddon($addonCode)->getAddonNamespace($namespace);
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
        $addons = AddonUtil::getAddonsDirs();
        $results = [];
        foreach ($addons as $addon) {
            $config = AddonUtil::readAddonConfig($addon);
            if (null === $config) {
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
        $addon = $this->getAddon($addonCode)->getAddons();

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
     * 获取资源路径.
     *
     * @param $addonCode
     * @param $key
     * @param $default
     *
     * @return string
     */
    public function getResponsePath($addonCode, $key, $default = null): string
    {
        return $this->getAddonPath($addonCode, data_get($this->getResponse($addonCode), $key, $default));
    }

    /**
     * 获取插件启动引导文件.
     *
     * @param $addonCode
     *
     * @return mixed|string
     */
    public function getAddonBootstrap($addonCode)
    {
        $path = $this->getAddonPath($addonCode, 'Bootstrap.php');
        if (!file_exists($path)) {
            return null;
        }
        $class = $this->getAddonNamespace($addonCode, 'Bootstrap');
        if (is_subclass_of($class, BaseBootstrap::class)) {
            return new $class();
        }

        return null;
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this->addonManager as $key => $value) {
            $data[$key] = $value->toArray();
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * 刷新缓存
     * 当插件安装、启用、禁用时需要刷新缓存.
     */
    public function refreshCache(): void
    {
        $this->reset();
        $content = "<?php\nreturn ".$this.';';
        file_put_contents(AddonUtil::getAddonCacheDir(), $content);
    }

    public function reset(): void
    {
        clearstatcache();
        $this->addonManager = [];
        $this->loadConfig(AddonUtil::getAddonsDirs());
    }

    /**
     * 清理缓存数据.
     */
    public function clearCache(): void
    {
        @unlink(AddonUtil::getAddonCacheDir());
    }

    public function getAddonManager(string $addonCode): AddonConfigManager
    {
        if (!$this->hasAddon($addonCode)) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        return $this->addonManager[$addonCode];
    }

    /**
     * 加载配置文件.
     *
     * @param array $dirs
     */
    private function loadConfig(array $dirs): void
    {
        foreach ($dirs as $dir) {
            if ($this->isAddonDisable($dir)) {
                continue;
            }
            $config = AddonUtil::readAddonConfig($dir);
            if (null === $config) {
                continue;
            }
            if (isset($this->addonManager[$config['code']])) {
                throw new AddonException("插件代码【{$config['code']}】重复定义");
            }
            $this->addonManager[$config['code']] = new AddonConfigManager($config);
        }
    }

    /**
     * 通过缓存加载配置.
     *
     * @param $data
     */
    private function loadCacheConfig($data): void
    {
        foreach ($data as $key => $config) {
            $this->addonManager[$key] = new AddonConfigManager($config);
        }
    }

    /**
     * 初始化项目.
     */
    private function initialize(): void
    {
        if (true === (bool) config('app.debug') && file_exists(AddonUtil::getAddonCacheDir())) {
            $data = require_once AddonUtil::getAddonCacheDir();
            $this->loadCacheConfig($data);

            return;
        }

        $this->loadConfig(AddonUtil::getAddonsDirs());
    }
}
