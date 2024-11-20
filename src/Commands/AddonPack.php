<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

namespace PTAdmin\Addon\Commands;


/**
 * 插件打包
 */
class AddonPack extends BaseAddonCommand
{
    protected $signature = 'addon:pack {--c|code : 应用编码}';
    protected $description = '打包插件应用';
}