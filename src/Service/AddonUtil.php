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

class AddonUtil
{
    /** @var string 插件目录基础路径 */
    public const ADDON_DIR = 'addons';

    /**
     * 获取缓存文件路径.
     *
     * @return string
     */
    public static function getAddonCacheDir(): string
    {
        return base_path('bootstrap'.\DIRECTORY_SEPARATOR.'cache'.\DIRECTORY_SEPARATOR.'addons.php');
    }

    /**
     * 扫描所有的插件完整目录.
     *
     * @return array
     */
    public static function getAddonsDirs(): array
    {
        $addons = [];
        $dirs = self::scanAddonsPath();
        foreach ($dirs as $dir) {
            $addon_path = base_path('addons'.\DIRECTORY_SEPARATOR.$dir);
            if (!is_dir($addon_path)) {
                continue;
            }
            $addons[] = $addon_path;
        }

        return $addons;
    }

    /**
     * 扫描插件目录.
     *
     * @return array
     */
    public static function scanAddonsPath(): array
    {
        $dirs = array_diff(scandir(base_path(self::ADDON_DIR)), ['.', '..', '.gitkeep', '.gitignore']);
        if (0 === \count($dirs)) {
            return [];
        }

        return $dirs;
    }

    /**
     * 基于文件内容计算文件夹MD5.
     *
     * @param $folderPath
     * @param array|string $exclude 排除需要计算的文件
     *
     * @return false|string
     */
    public static function getFolderMd5($folderPath, $exclude = null)
    {
        if (!is_dir($folderPath)) {
            return false;
        }
        $md5Hashes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPath));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $md5Hashes[] = md5_file($file->getRealPath());
            }
        }
        sort($md5Hashes);

        return md5(implode('', $md5Hashes));
    }

    private function excludeMd5($file, $exclude): bool
    {
        return false;
    }
}
