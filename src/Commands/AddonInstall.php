<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

namespace PTAdmin\Addon\Commands;

/**
 * 插件安装
 */
class AddonInstall extends BaseAddonCommand
{
    protected $signature = 'addon:install {code : 应用编码} {--f|force=false : 强制覆盖}';
    protected $description = '安装插件应用';


    public function handle(): int
    {
        return 0;
    }
}