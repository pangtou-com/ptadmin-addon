<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

namespace PTAdmin\Addon\Commands;

/**
 * 插件上传
 */
class AddonUpload extends BaseAddonCommand
{
    protected $signature = 'addon:upload {--c|code : 应用编码}';
    protected $description = '上传应用到平台';
}