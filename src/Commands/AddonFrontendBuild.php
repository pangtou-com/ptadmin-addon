<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonFrontendBuild extends BaseAddonCommand
{
    protected $signature = 'addon:frontend:build
        {code : 插件编码}
        {--package-manager= : 指定包管理器}
        {--script=build : 指定构建脚本}
        {--skip-install : 跳过依赖安装}';

    protected $description = '构建插件前端资源并生成模块清单';

    public function handle(): int
    {
        AddonAction::buildFrontend(
            strtolower((string) $this->argument('code')),
            (string) $this->option('package-manager'),
            (string) $this->option('script'),
            (bool) $this->option('skip-install')
        );

        return 0;
    }
}
