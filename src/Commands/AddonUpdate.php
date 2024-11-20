<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

namespace PTAdmin\Addon\Commands;

/**
 * 插件更新
 */
class AddonUpdate extends BaseAddonCommand
{
    protected $signature = 'addon:update {--c|code : 应用编码} {--f|force : 强制覆盖}';
    protected $description = '更新插件应用';

    public function handle(): int
    {
        return 0;
    }
}