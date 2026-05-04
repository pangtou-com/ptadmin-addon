<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonResourcesSync extends BaseAddonCommand
{
    protected $signature = 'addon:resources:sync {code : 插件编码}';
    protected $description = '同步插件后台资源定义';

    public function handle(): int
    {
        AddonAction::syncResources(strtolower((string) $this->argument('code')));

        return 0;
    }
}
