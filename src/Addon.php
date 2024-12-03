<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/12/3
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
 * @method static mixed getInject($addonCode)
 * @method static mixed getResponse($addonCode) 获取资源信息
 * @method static mixed getResponsePath($addonCode, $key, $default = null) 获取资源路径
 * @method static mixed getDirective($addonCode)
 * @method static mixed getAddon($addonCode)
 * @method static string getAddonPath($addonCode, $path = null)
 * @method static bool checkAddonVersion($addonName, $version)
 * @method static array getInstalledAddonsCode() 本地已安装插件code
 * @method static array getInstalledAddons() 本地已安装插件信息
 * @method static array getAddonRequired($addonCode)
 * @method static bool hasAddonRequired($addonCode)
 * @method static bool addonRequired($addonCode)
 * @method static string|null getAddonVersion($addonCode)
 * @see AddonManager
 */
class Addon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return "addon";
    }
}