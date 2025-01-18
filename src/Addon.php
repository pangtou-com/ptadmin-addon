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

namespace PTAdmin\Addon;

use Illuminate\Support\Facades\Facade;
use PTAdmin\Addon\Service\AddonConfigManager;
use PTAdmin\Addon\Service\AddonDirectivesActuator;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\BaseBootstrap;
use PTAdmin\Addon\Service\DirectivesDTO;

/**
 * @method static AddonConfigManager getAddonManager()                     插件配置管理对象
 * @method static bool isAddonDisable($addonDir)                           判断插件是否启用
 * @method static void reset()                                             重置插件管理
 * @method static bool hasAddon($addonCode)                                判断是否存在插件
 * @method static array getProviders()                                     获取所有插件的提供者信息
 * @method static array getInjects($type = null)                           获取所有插件的注入信息
 * @method static array getResponses()                                     获取所有插件的资源路径
 * @method static array getDirectives()                                    获取所有插件的指令信息
 * @method static array getAddons()                                        获取所有插件应用的配置内容
 * @method static mixed getProvider($addonCode)                            获取指定插件的提供者信息
 * @method static mixed getInject($addonCode)                              获取指定插件的注入信息
 * @method static mixed getResponse($addonCode)                            获取指定插件的资源信息
 * @method static mixed getResponsePath($addonCode, $key, $default = null) 获取获取指定插件的资源路径
 * @method static mixed getDirective($addonCode)                           获取指定插件指令信息
 * @method static AddonConfigManager getAddon($addonCode)                  获取指定插件的应用配置
 * @method static string getAddonPath($addonCode, $path = null)            获取指定插件的路径
 * @method static bool checkAddonVersion($addonName, $version)
 * @method static array getInstalledAddonsCode()                           本地已安装插件code
 * @method static array getInstalledAddons()                               本地已安装插件信息
 * @method static array getAddonRequired($addonCode)
 * @method static bool hasAddonRequired($addonCode)
 * @method static bool addonRequired($addonCode)
 * @method static string getAddonNamespace($addonCode, $namespace = null)  获取插件内的命名空间
 * @method static BaseBootstrap getAddonBootstrap($addonCode)              获取插件启动类
 * @method static string|null getAddonVersion($addonCode)                  获取插件当前版本
 * @method static void refreshCache()                                      刷新插件缓存
 * @method static void clearCache()                                        清理插件缓存
 *
 * @see AddonManager
 */
class Addon extends Facade
{
    /**
     * 使用代码的方式调用指令.
     *
     * @param string $addonCode 插件code
     * @param string $method    指令方法
     * @param array  $params
     *
     * @return null|mixed
     */
    public static function execute(string $addonCode, string $method, array $params = [])
    {
        return AddonDirectivesActuator::handle($addonCode, $method, DirectivesDTO::build($params));
    }

    /**
     * 获取支持的支付.
     *
     * @return array
     */
    public static function getInjectPayment(): array
    {
        return self::getInjects('payment');
    }

    /**
     * 获取支持的第三方授权.
     *
     * @return array
     */
    public static function getInjectAuth(): array
    {
        return self::getInjects('auth');
    }

    /**
     * 获取支持的第三方短信服务.
     *
     * @return array
     */
    public static function getInjectSMS(): array
    {
        return self::getInjects('sms');
    }

    /**
     * 获取支持的第三方存储服务.
     *
     * @return array
     */
    public static function getInjectStorage(): array
    {
        return self::getInjects('storage');
    }

    protected static function getFacadeAccessor(): string
    {
        return 'addon';
    }
}
