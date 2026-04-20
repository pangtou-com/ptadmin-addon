<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonInit extends BaseAddonCommand
{
    protected $signature = 'addon:init {code : 插件编码} {--title= : 插件标题} {--f|force : 强制覆盖已存在目录}';
    protected $description = '初始化插件开发脚手架';

    public function handle(): int
    {
        AddonAction::init(
            strtolower((string) $this->argument('code')),
            (string) $this->option('title'),
            (bool) $this->option('force')
        );

        return 0;
    }
}
