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

/**
 * 插件启动基类.
 */
abstract class BaseBootstrap
{
    /** @var array 管理后台菜单目录 */
    // ['name' => ''， 'title' => '', 'icon' => '', 'route' => '', 'type' => '', 'is_nav' => 1, 'weight' => 99, 'note' => '']
    public $admin_menu = [];

    /** @var string 管理后台的父级菜单，当父级菜单不存在时则默认为插件为父级菜单 */
    public $admin_parent_menu;

    /**
     * 插件安装.成功后调用.
     */
    public function install(): void
    {
    }

    /**
     * 插件卸载.
     */
    public function uninstall(): void
    {
    }

    /**
     * 插件启用之前调用.
     */
    public function enable(): void
    {
    }

    /**
     * 插件禁用.
     */
    public function disable(): void
    {
    }

    /**
     * 插件升级.
     */
    public function upgrade(): void
    {
    }
}
