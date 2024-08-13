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

use Illuminate\Support\Str;
use PTAdmin\Addon\Providers\BaseAddonService;

/**
 * 插件启动管理.
 */
final class BootstrapManage
{
    /**
     * 初始化插件信息.
     */
    public static function getServiceRegister(): array
    {
        $instance = new self();
        if (!(bool) config('app.debug')) {
            $data = $instance->getAddonsCache();
            if (\count($data) > 0) {
                return $data;
            }
        }

        return $instance->getAddonService();
    }

    /**
     * 刷新缓存
     * 当插件安装、启用、禁用时需要刷新缓存.
     */
    public static function reCache(): void
    {
        (new self())->setAddonsCache(self::getServiceRegister());
    }

    /**
     * 获取启用的插件服务.
     *
     * @return array
     */
    private function getAddonService(): array
    {
        $addons = $this->getAddonsFolders();
        if (0 === \count($addons)) {
            return [];
        }
        // 加载服务注册和配置信息
        $register = [];
        foreach ($addons as $addon) {
            if (file_exists($this->getAddonsDirs($addon.\DIRECTORY_SEPARATOR.'disable'))) {
                continue;
            }
            $dir = $this->getAddonsDirs($addon.\DIRECTORY_SEPARATOR.'Providers');
            if (!is_dir($dir)) {
                continue;
            }

            $files = array_diff(scandir($dir), ['.', '..', '.gitkeep']);
            foreach ($files as $item) {
                if (!Str::endsWith($item, '.php')) {
                    continue;
                }
                $class = 'Addon\\'.$addon.'\\Providers\\'.str_replace('.php', '', $item);
                if (is_subclass_of($class, BaseAddonService::class)) {
                    $register[] = $class;
                }
            }
        }

        return $register;
    }

    /**
     * 通过缓存获取插件目录信息.
     *
     * @return string
     */
    private function getCacheDir(): string
    {
        return base_path('bootstrap'.\DIRECTORY_SEPARATOR.'cache'.\DIRECTORY_SEPARATOR.'addons.php');
    }

    /**
     * 获取插件缓存信息.
     *
     * @return array
     */
    private function getAddonsCache(): array
    {
        $cacheDir = $this->getCacheDir();
        if (file_exists($cacheDir)) {
            return require_once $cacheDir;
        }

        return [];
    }

    /**
     * 设置插件目录缓存信息.
     *
     * @param array $addons
     */
    private function setAddonsCache(array $addons): void
    {
        $cacheDir = $this->getCacheDir();
        if (file_exists($cacheDir)) {
            unlink($cacheDir);
        }
        $content = "<?php\nreturn ".var_export($addons, true).';';
        file_put_contents($cacheDir, $content);
    }

    /**
     * 获取插件目录信息.
     *
     * @return array
     */
    private function getAddonsFolders(): array
    {
        $addonsDir = $this->getAddonsDirs();
        $addons = [];
        if (is_dir($addonsDir)) {
            $addons = array_diff(scandir($addonsDir), ['.', '..', '.gitkeep']);
            $addons = array_filter($addons, function ($item) {
                return is_dir($this->getAddonsDirs($item));
            });
        }

        return $addons;
    }

    /**
     * 获取插件目录信息.
     *
     * @param null|string $path
     *
     * @return string
     */
    private function getAddonsDirs(string $path = null): string
    {
        return base_path('addons'.($path ? \DIRECTORY_SEPARATOR.$path : $path));
    }
}
