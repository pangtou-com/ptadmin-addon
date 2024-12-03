<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/14
 */

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\BootstrapManage;

class AddonCacheClear extends BaseAddonCommand
{
    protected $signature = 'addon:cache-clear';
    protected $description = '清理应用缓存';

    public function handle(): int
    {
        BootstrapManage::clearCache();
        $this->info('插件缓存清理成功');
        return 0;
    }
}