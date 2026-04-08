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

final class AddonUninstall extends AbstractAddonAction
{
    public function handle(): ?bool
    {
        $this->info('开始卸载插件');
        $installer = Addon::getAddonInstaller($this->code);
        if (null !== $installer) {
            try {
                $installer->uninstall();
            } catch (\Exception $exception) {
                $this->error('插件卸载失败，请检查插件是否正确安装');
                $this->error($exception->getMessage());

                return null;
            }
        }
        $this->info('开始删除插件文件');
        $this->filesystem->deleteDirectory(Addon::getAddonPath($this->code));
        $this->info('插件卸载完成');

        return true;
    }
}
