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

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件安装.
 */
class AddonInstall
{
    /** @var BaseBootstrap 插件启用服务类 */
    private $addonBootstrap;
    private $addonName;
    private $addonInfo;

    private function __construct()
    {
    }

    public static function make($addonName): self
    {
        $instance = new self();
        $instance->addonName = $addonName;
        $instance->initialize($addonName);

        return $instance;
    }

    /**
     * 安装插件.
     */
    public function install(): bool
    {
        // 1、插件安装之前调用
        if (method_exists($this->addonBootstrap, 'beforeInstall')) {
            // 如果返回false则不继续安装
            if (false === $this->addonBootstrap->beforeInstall()) {
                return false;
            }
        }
        // 2、执行插件SQL安装
        if (($sql = $this->getAddonInstallSql()) !== null) {
            app(Database::class)->restoreData($sql);
        }

        // 3、管理菜单添加。
        $menu = $this->addonBootstrap->admin_menu;
        if (\is_array($menu) && \count($menu) > 0) {
            // PermissionService::addonInstallMenu($this->addonInfo, $menu, $this->addonBootstrap->admin_parent_menu);
        }

        // 4、安装完成后调用, 用于执行一些后续操作
        $this->addonBootstrap->install();

        return true;
    }

    /**
     * 卸载插件.
     *
     * @param bool $force 强制卸载
     */
    public function uninstall(bool $force = false): bool
    {
        // 插件卸载之前调用
        if (method_exists($this->addonBootstrap, 'beforeUninstall')) {
            $this->addonBootstrap->beforeUninstall();
        }
        // 校验是否可以卸载，当插件作为其他插件的依赖时，不允许卸载
        if (!$force && $this->uninstallCheck()) {
            throw new AddonException('插件作为其他插件的依赖，不允许卸载');
        }
        // 删除插件菜单
        // PermissionService::addonUninstallMenu($this->addonName);
        // 删除插件目录
        $this->rmAddonPath();

        return true;
    }

    /**
     * 启用插件.
     */
    public function enable(): void
    {
        if (method_exists($this->addonBootstrap, 'beforeEnable')) {
            $this->addonBootstrap->beforeEnable();
        }
        if (!AddonManager::getInstance()->addonRequired($this->addonName)) {
            throw new \PTAdmin\Addon\Exception\AddonException('插件缺少依赖');
        }
        $this->addonBootstrap->enable();

        @unlink($this->addonInfo['base_path'].\DIRECTORY_SEPARATOR.'disable');

        BootstrapManage::reCache();
    }

    /**
     * 禁用插件.
     */
    public function disable(): void
    {
        $this->addonBootstrap->disable();
        @touch($this->addonInfo['base_path'].\DIRECTORY_SEPARATOR.'disable');
        BootstrapManage::reCache();
    }

    /**
     * 插件升级.
     */
    public function upgrade(): void
    {
        if (method_exists($this->addonBootstrap, 'beforeUpgrade')) {
            $this->addonBootstrap->beforeUpgrade();
        }

        $this->addonBootstrap->upgrade();
    }

    /**
     * 获取插件安装SQL文件路径.
     *
     * @return string
     */
    private function getAddonInstallSql(): ?string
    {
        $sql = $this->addonInfo['base_path'].\DIRECTORY_SEPARATOR.'install.sql';
        if (file_exists($sql)) {
            return $sql;
        }

        return null;
    }

    private function initialize($addonName): void
    {
        $addonInfo = AddonManager::getInstance()->getInstalledAddons();

        $addonInfo = $addonInfo[$addonName] ?? null;
        if (null === $addonInfo) {
            throw new AddonException("未定义的插件【{$addonName}】");
        }
        $class = 'Addon\\'.$addonInfo['addon_path'].'\\Bootstrap';
        $this->addonInfo = $addonInfo;

        try {
            $this->addonBootstrap = (new \ReflectionClass($class))->newInstance();
        } catch (\ReflectionException $e) {
            throw new AddonException("插件【{$addonName}】启动类不存在");
        }
    }

    /**
     * 卸载校验.
     *
     * @return bool
     */
    private function uninstallCheck(): bool
    {
        return AddonManager::getInstance()->hasAddonRequired($this->addonName);
    }

    /**
     * 删除插件目录.
     */
    private function rmAddonPath(): void
    {
        (new Filesystem())->deleteDirectory($this->addonInfo['base_path']);
    }
}
