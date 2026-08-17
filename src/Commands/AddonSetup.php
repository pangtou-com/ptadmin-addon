<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

/**
 * 初始化已经存在于 addons 目录中的插件。
 */
final class AddonSetup extends BaseAddonCommand
{
    protected $signature = 'addon:setup {code : 应用编码} {--f|force : 强制重新执行安装生命周期}';
    protected $description = '初始化已部署插件，不覆盖插件目录';

    public function handle(): int
    {
        AddonAction::setup((string) $this->argument('code'), (bool) $this->option('force'));

        return 0;
    }
}
