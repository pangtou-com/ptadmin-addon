<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonFrontendPull extends BaseAddonCommand
{
    protected $signature = 'addon:frontend:pull
        {code : 插件编码}
        {--template=module : 前端模板标识，默认 module，可选 micro-app}
        {--ref=main : 模板版本或分支}
        {--source= : 指定模板源，不指定时按区域自动选择}
        {--f|force : 强制覆盖已存在 Frontend 目录}';

    protected $description = '拉取插件前端模板到 Frontend 目录';

    public function handle(): int
    {
        AddonAction::pullFrontend(
            strtolower((string) $this->argument('code')),
            (string) $this->option('template'),
            (string) $this->option('ref'),
            (string) $this->option('source'),
            (bool) $this->option('force')
        );

        return 0;
    }
}
