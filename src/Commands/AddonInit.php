<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/14
 */

namespace PTAdmin\Addon\Commands;

class AddonInit extends BaseAddonCommand
{
    protected $signature = 'addon:init {code : 应用编码}';
    protected $description = '初始化应用';


    public function handle(): int
    {
        return 0;
    }
}