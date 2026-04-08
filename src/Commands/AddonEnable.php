<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonEnable extends BaseAddonCommand
{
    protected $signature = 'addon:enable {code : 应用编码}';
    protected $description = '启用插件应用';

    public function handle(): int
    {
        AddonAction::enable((string) $this->argument('code'));

        return 0;
    }
}
