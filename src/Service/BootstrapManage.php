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

use Illuminate\Support\Arr;
use PTAdmin\Addon\Addon;

/**
 * 插件启动管理.
 */
final class BootstrapManage
{

    /**
     * 注册插件应用的服务提供者
     * @param $app
     * @return array
     */
    public static function registerProvider($app): array
    {
        $wait_booting = [];
        $providers = Addon::getProviders();
        foreach ($providers as $key => $item) {
            $item = Arr::wrap($item);
            $isBoot = false;
            foreach ($item as $val) {
                $provider = $app->register($val);
                if (method_exists($provider, "boot")) {
                    $isBoot = true;
                }
            }
            if (!$isBoot) {
                $wait_booting[] = $key;
            }
        }

        return $wait_booting;
    }

    /**
     * 刷新缓存
     * 当插件安装、启用、禁用时需要刷新缓存.
     */
    public static function refreshCache(): void
    {
        (new self())->setAddonsCache(Addon::getAddonManager());
    }

    public static function clearCache(){
        $path = (new self())->getCacheDir();
        @unlink($path);
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
     * 设置插件目录缓存信息.
     *
     * @param $addons
     */
    private function setAddonsCache($addons): void
    {
        $cacheDir = $this->getCacheDir();
        if (file_exists($cacheDir)) {
            unlink($cacheDir);
        }
        $content = "<?php\nreturn ".$addons.';';
        file_put_contents($cacheDir, $content);
    }
}
