<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Service\Action\AddonAction;

class AddonDisable extends BaseAddonCommand
{
    protected $signature = 'addon:disable {code : 应用编码}';
    protected $description = '禁用插件应用';

    public function handle(): int
    {
        AddonAction::disable((string) $this->argument('code'));

        return 0;
    }
}
