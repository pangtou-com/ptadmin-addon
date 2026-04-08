<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Addon】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Addon;

use Illuminate\Support\Facades\Facade;
use PTAdmin\Addon\Service\AddonConfigManager;
use PTAdmin\Addon\Service\AddonDirectivesActuator;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonInjectsActuator;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\BaseBootstrap;
use PTAdmin\Addon\Service\BaseInstaller;
use PTAdmin\Addon\Service\DirectivesDTO;
use PTAdmin\Addon\Service\PaymentGateway;

/**
 * @method static AddonConfigManager getAddonManager()                     插件配置管理对象
 * @method static bool isAddonDisable($addonDir)                           判断插件是否启用
 * @method static void reset()                                             重置插件管理
 * @method static bool hasAddon($addonCode)                                判断是否存在插件
 * @method static array getProviders()                                     获取所有插件的提供者信息
 * @method static array getInjects($type = null)                           获取所有插件的注入信息
 * @method static array getHooks()                                         获取所有插件的 Hook 信息
 * @method static array getResponses()                                     获取所有插件的资源路径
 * @method static array getDirectives()                                    获取所有插件的指令信息
 * @method static array getAddons()                                        获取所有插件应用的配置内容
 * @method static mixed getProvider($addonCode)                            获取指定插件的提供者信息
 * @method static mixed getInject($addonCode)                              获取指定插件的注入信息
 * @method static mixed getHook($addonCode)                                获取指定插件的 Hook 信息
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
 * @method static bool hasInstalledAddon($addonCode)
 * @method static bool addonRequired($addonCode)
 * @method static string getAddonNamespace($addonCode, $namespace = null)  获取插件内的命名空间
 * @method static BaseBootstrap getAddonBootstrap($addonCode)              获取插件启动类
 * @method static BaseInstaller getAddonInstaller($addonCode)              获取插件安装器
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
     * 触发插件 Hook.
     *
     * @param string $event
     * @param array  $payload
     *
     * @return array
     */
    public static function triggerHook(string $event, array $payload = []): array
    {
        return AddonHooksManage::getInstance()->dispatch($event, $payload);
    }

    /**
     * 调用插件注入能力.
     *
     * @param string      $group   inject 分组，如 payment、auth、notify、storage
     * @param string      $code    inject 编码
     * @param array       $payload
     * @param string|null $action  能力动作，如 create、refund、send
     *
     * @return mixed
     */
    public static function executeInject(string $group, string $code, array $payload = [], ?string $action = null)
    {
        return AddonInjectsActuator::handle($group, $code, $payload, $action);
    }

    /**
     * 获取默认或指定插件的支付能力代理。
     */
    public static function payment(?string $addonCode = null, ?string $code = null): PaymentGateway
    {
        return new PaymentGateway($addonCode, $code);
    }

    /**
     * 获取当前所有可用支付插件代理。
     *
     * @return array
     */
    public static function payments(?string $addonCode = null): array
    {
        if (!blank($addonCode)) {
            return AddonInjectsManage::getInstance()->getDefinitionsByAddonCode('payment', $addonCode);
        }

        return AddonInjectsManage::getInstance()->getDefinitionsByGroup('payment');
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

    /**
     * 获取支持的消息通知服务.
     *
     * @return array
     */
    public static function getInjectNotify(): array
    {
        return self::getInjects('notify');
    }

    protected static function getFacadeAccessor(): string
    {
        return 'addon';
    }
}
