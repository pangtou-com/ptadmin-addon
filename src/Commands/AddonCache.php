<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/14
 */

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\BootstrapManage;

class AddonCache extends BaseAddonCommand
{
    protected $signature = 'addon:cache';
    protected $description = '缓存应用';

    public function handle(): int
    {
        BootstrapManage::refreshCache();
        $this->info('插件缓存刷新成功');
        return 0;
    }
}