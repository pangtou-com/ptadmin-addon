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

namespace PTAdmin\Addon\Service\Action;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonAdminResourceSynchronizer;
use PTAdmin\Addon\Service\Database;

/**
 * 插件安装.
 */
final class AddonInstall extends AbstractAddonAction
{
    private $installer;

    public function handle(): ?bool
    {
        $this->info(__('ptadmin-addon::messages.action.install_start'));
        $this->installer = Addon::getAddonInstaller($this->code);
        try {
            if (false === $this->beforeInstall()) {
                return null;
            }
            $this->installSql();
            if (null !== $this->installer) {
                $this->installer->install();
                $this->installer->init();
            }
            app(AddonAdminResourceSynchronizer::class)->sync($this->code);
        } catch (\Throwable $exception) {
            $this->rollbackInstalledAddon();

            throw $exception instanceof AddonException
                ? $exception
                : new AddonException($exception->getMessage(), 20000, $exception);
        }
        $this->info(__('ptadmin-addon::messages.action.install_success'));

        return true;
    }

    /**
     * 插件安装之前.
     */
    public function beforeInstall(): bool
    {
        if (null !== $this->installer && false === $this->installer->beforeInstall()) {
            $this->error(__('ptadmin-addon::messages.action.install_failed'));

            return false;
        }

        return true;
    }

    /**
     * 安装sql.
     */
    private function installSql(): void
    {
        $sql = Addon::getAddonPath($this->code, 'install.sql');
        if (is_file($sql) && file_exists($sql)) {
            $this->info(__('ptadmin-addon::messages.action.import_data'));
            app(Database::class)->restoreData($sql);
        }
    }

    private function rollbackInstalledAddon(): void
    {
        $targetPath = null;
        if (Addon::hasInstalledAddon($this->code)) {
            $targetPath = Addon::getAddonPath($this->code);
        } else {
            $addon = Addon::getInstalledAddons()[$this->code] ?? null;
            if (null !== $addon) {
                $targetPath = base_path('addons'.\DIRECTORY_SEPARATOR.$addon['base_path']);
            }
        }

        $backupPath = $this->action->findBackupAddonPath();
        if (null !== $targetPath && is_dir($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }
        if (null === $backupPath) {
            Addon::reset();

            return;
        }

        $restorePath = base_path('addons'.\DIRECTORY_SEPARATOR.basename($backupPath));
        $this->filesystem->ensureDirectoryExists(\dirname($restorePath));
        $this->filesystem->moveDirectory($backupPath, $restorePath);
        Addon::reset();
    }
}
