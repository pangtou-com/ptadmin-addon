<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonInstallLocal extends BaseAddonCommand
{
    protected $signature = 'addon:install-local {file : 本地插件 zip 包路径} {--f|force : 强制覆盖已安装插件}';
    protected $description = '从本地 zip 包安装插件';

    public function handle(): int
    {
        AddonAction::installLocal((string) $this->argument('file'), (bool) $this->option('force'));

        return 0;
    }
}
