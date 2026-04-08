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

    public function getInjects($type = null): array
    {
        return AddonInjectsManage::getInstance()->getInjects($type);
    }

    public function getInject($addonCode): array
    {
        return AddonInjectsManage::getInstance()->getInject($addonCode);
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
        return AddonDirectivesManage::getInstance()->getAll();
    }

    public function getDirective($addonCode): array
    {
        return AddonDirectivesManage::getInstance()->getDirectives($addonCode);
    }

    public function getHooks(): array
    {
        return AddonHooksManage::getInstance()->getAll();
    }

    public function getHook($addonCode): array
    {
        return AddonHooksManage::getInstance()->getHooks($addonCode);
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
        if ($this->hasAddon($addonCode)) {
            return $this->getAddon($addonCode)->getAddonPath($path);
        }

        $addon = $this->getInstalledAddonConfig($addonCode);
        if (null === $addon) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        return base_path('addons'.\DIRECTORY_SEPARATOR.$addon['base_path'].(null !== $path ? \DIRECTORY_SEPARATOR.$path : ''));
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
        if ($this->hasAddon($addonCode)) {
            return $this->getAddon($addonCode)->getAddonNamespace($namespace);
        }

        $addon = $this->getInstalledAddonConfig($addonCode);
        if (null === $addon) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        return 'Addon\\'.$addon['base_path'].($namespace ? '\\'.$namespace : '');
    }

    public function getAddonInstaller($addonCode)
    {
        return $this->resolveAddonEntry($addonCode, 'installer', BaseInstaller::class, 'Installer');
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

        return data_get($addon, 'dependencies.plugins', $addon['require'] ?? []);
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
            $required = data_get($value, 'addons.dependencies.plugins', $value['addons']['require'] ?? []);
            foreach ($required as $key => $dependency) {
                if ((\is_int($key) && $dependency === $addonCode) || $key === $addonCode) {
                    return true;
                }
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
            if (\is_int($key)) {
                if (!$this->hasAddon($val)) {
                    return false;
                }

                continue;
            }
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
        if ($this->hasAddon($addonCode)) {
            $addon = $this->getAddon($addonCode);

            return $addon->getVersion();
        }

        $addon = $this->getInstalledAddonConfig($addonCode);

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
        return $this->resolveAddonEntry($addonCode, 'bootstrap', BaseBootstrap::class, 'Bootstrap');
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this->addonManager as $key => $value) {
            $data[$key] = array_merge($value->toArray(), [
                'inject' => $this->getInject($key),
                'directives' => $this->getDirective($key),
                'hooks' => $this->getHook($key),
            ]);
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
        $cacheFile = AddonUtil::getAddonCacheDir();
        $cacheDir = \dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, $content);
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

    public function hasInstalledAddon(string $addonCode): bool
    {
        return null !== $this->getInstalledAddonConfig($addonCode);
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
            $this->addonManager[$key] = new AddonConfigManager($config['addons'] ?? $config);
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

    private function getInstalledAddonConfig(string $addonCode): ?array
    {
        $addons = $this->getInstalledAddons();

        return $addons[$addonCode] ?? null;
    }

    private function resolveAddonEntry(string $addonCode, string $entryKey, string $baseClass, string $defaultClass): ?object
    {
        $class = $this->getAddonEntryClass($addonCode, $entryKey, $defaultClass);
        if (null === $class) {
            return null;
        }

        $path = $this->getAddonEntryPath($addonCode, $class);
        if (null !== $path && file_exists($path) && !class_exists($class, false)) {
            require_once $path;
        }

        if (is_subclass_of($class, $baseClass)) {
            return new $class();
        }

        return null;
    }

    private function getAddonEntryClass(string $addonCode, string $entryKey, string $defaultClass): ?string
    {
        if ($this->hasAddon($addonCode)) {
            $entry = $this->getAddon($addonCode)->getEntry($entryKey);
            if (\is_string($entry) && '' !== $entry) {
                return $entry;
            }

            return $this->getAddonNamespace($addonCode, $defaultClass);
        }

        $addon = $this->getInstalledAddonConfig($addonCode);
        if (null === $addon) {
            throw new AddonException("未定义的插件【{$addonCode}】");
        }

        $entry = data_get($addon, 'entry.'.$entryKey);
        if (\is_string($entry) && '' !== $entry) {
            return $entry;
        }

        return 'Addon\\'.$addon['base_path'].'\\'.$defaultClass;
    }

    private function getAddonEntryPath(string $addonCode, string $class): ?string
    {
        $addon = $this->getInstalledAddonConfig($addonCode);
        if (null === $addon) {
            return null;
        }

        $prefix = 'Addon\\'.$addon['base_path'].'\\';
        if (0 !== strpos($class, $prefix)) {
            return null;
        }

        $relative = str_replace('\\', \DIRECTORY_SEPARATOR, substr($class, \strlen($prefix)));

        return $this->getAddonPath($addonCode, $relative.'.php');
    }
}
