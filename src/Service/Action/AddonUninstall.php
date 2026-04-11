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

namespace PTAdmin\Addon\Service\Action;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Service\AddonAdminResourceSynchronizer;

final class AddonUninstall extends AbstractAddonAction
{
    public function handle(): ?bool
    {
        $this->info(__('ptadmin-addon::messages.action.uninstall_start'));
        $installer = Addon::getAddonInstaller($this->code);
        if (null !== $installer) {
            try {
                $installer->uninstall();
            } catch (\Exception $exception) {
                $this->error(__('ptadmin-addon::messages.action.uninstall_failed'));
                $this->error($exception->getMessage());

                return null;
            }
        }
        app(AddonAdminResourceSynchronizer::class)->delete($this->code);
        $this->info(__('ptadmin-addon::messages.action.delete_files'));
        $this->filesystem->deleteDirectory(Addon::getAddonPath($this->code));
        $this->info(__('ptadmin-addon::messages.action.uninstall_done'));

        return true;
    }
}
