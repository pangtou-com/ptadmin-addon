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

namespace PTAdmin\Addon;

use Illuminate\Support\Facades\Facade;
use PTAdmin\Addon\Service\AddonConfigManager;
use PTAdmin\Addon\Service\AddonManager;

/**
 * @method static AddonConfigManager getAddonManager()
 * @method static void boot()
 * @method static bool isAddonDisable($addonDir)
 * @method static mixed execute($addonName, $method, array $params = [])
 * @method static bool hasAddon($addonCode)
 * @method static array getProviders()
 * @method static array getInjects()
 * @method static array getResponses()
 * @method static array getDirectives()
 * @method static array getAddons()
 * @method static mixed getProvider($addonCode)
 * @method static mixed getInject($code)
 * @method static mixed getResponse($addonCode)                            获取资源信息
 * @method static mixed getResponsePath($addonCode, $key, $default = null) 获取资源路径
 * @method static mixed getDirective($addonCode)
 * @method static mixed getAddon($addonCode, $key = null, $default = null)
 * @method static string getAddonPath($addonCode, $path = null)
 * @method static bool checkAddonVersion($addonName, $version)
 * @method static array getInstalledAddonsCode()                           本地已安装插件code
 * @method static array getInstalledAddons()                               本地已安装插件信息
 * @method static array getAddonRequired($addonCode)
 * @method static bool hasAddonRequired($addonCode)
 * @method static bool addonRequired($addonCode)
 * @method static string|null getAddonVersion($addonCode)                  根据插件ID获取插件当前版本
 * @method static void refreshCache()                                      刷新插件缓存
 * @method static void clearCache()                                        清理插件缓存
 *
 * @see AddonManager
 */
class Addon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'addon';
    }
}
