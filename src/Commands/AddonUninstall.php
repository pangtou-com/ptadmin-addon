<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

namespace PTAdmin\Addon\Commands;

/**
 * 插件卸载
 */
class AddonUninstall extends BaseAddonCommand
{
    protected $signature = 'addon:uninstall {--c|code : 应用编码} {--f|force : 强制覆盖}';
    protected $description = '卸载插件应用';

}