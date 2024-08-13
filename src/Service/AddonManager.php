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
    // 实例
    private static $instance;
    // 插件集合
    private $addon_maps = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function insert($provider): void
    {
        if (!method_exists($provider, 'getAddonName')) {
            return;
        }
        if (!method_exists($provider, 'getAddonInfo')) {
            return;
        }
        $this->addon_maps[$provider->getAddonName()] = $provider->getAddonInfo();
    }

    /**
     * 执行指令方法.
     *
     * @param $addonName
     * @param $method
     * @param array $params
     *
     * @return null|mixed
     */
    public function execute($addonName, $method, array $params = [])
    {
        if (!$this->hasAddon($addonName)) {
            throw new AddonException("未定义的插件【{$addonName}】");
        }

        return AddonDirectivesActuator::handle($addonName, $method, DirectivesDTO::build($params));
    }

    /**
     * 判断插件是否存在.
     *
     * @param $addonName
     *
     * @return bool
     */
    public function hasAddon($addonName): bool
    {
        return isset($this->addon_maps[$addonName]);
    }

    /**
     * 校验插件版本.
     *
     * @param $addonName
     * @param $version
     *
     * @return bool
     */
    public function checkAddonVersion($addonName, $version): bool
    {
        if (!$this->hasAddon($addonName)) {
            return false;
        }
        $var = $this->getAddonVersion($addonName);
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
        $infos = $this->getInstalledAddons();
        $codes = [];
        foreach ($infos as $info) {
            if (isset($info['code']) && $info['code']) {
                $codes[] = $info['code'];
            }
        }

        return $codes;
    }

    /**
     * 本地已安装插件信息.
     *
     * @return array
     */
    public function getInstalledAddons(): array
    {
        $dirs = array_diff(scandir(base_path('addons')), ['.', '..', '.gitkeep', '.gitignore']);
        if (0 === \count($dirs)) {
            return [];
        }
        $infos = [];
        foreach ($dirs as $dir) {
            $addon_path = addon_path($dir);
            if (!is_dir($addon_path)) {
                continue;
            }
            $info = parser_addon_ini($dir);
            if (\count($info) > 0) {
                $info['addon_path'] = $info['dir'] = $dir;
                $info['base_path'] = $addon_path;

                $infos[$info['code']] = $info;
            }
        }

        return $infos;
    }

    /**
     * 获取插件信息.
     *
     * @param $addonName
     *
     * @return array
     */
    public function getAddonInfo($addonName): array
    {
        return $this->addon_maps[$addonName] ?? [];
    }

    /**
     * 获取插件依赖.
     *
     * @param $addonName
     *
     * @return array
     */
    public function getAddonRequired($addonName): array
    {
        return $this->addon_maps[$addonName]['require'] ?? [];
    }

    /**
     * 判断当前插件是否属于必须插件.
     *
     * @param $addonName
     *
     * @return bool
     */
    public function hasAddonRequired($addonName): bool
    {
        foreach ($this->addon_maps as $value) {
            if (isset($value['require'], $value['require'][$addonName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断插件依赖是否满足.
     *
     * @param $addonName
     *
     * @return bool
     */
    public function addonRequired($addonName): bool
    {
        $required = $this->getAddonRequired($addonName);
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
     * @param $addonName
     *
     * @return null|string
     */
    public function getAddonVersion($addonName): ?string
    {
        return $this->addon_maps[$addonName]['version'] ?? null;
    }
}
