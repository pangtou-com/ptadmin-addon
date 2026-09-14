<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonAdminResourceSynchronizer;

final class AddonResourcesSyncAction extends AbstractAddonAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(bool $includeDisabled = false): array
    {
        if (!Addon::hasInstalledAddon($this->code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $this->code]));
        }

        $enabled = Addon::hasAddon($this->code);
        if (!$enabled && !$includeDisabled) {
            throw new AddonException(sprintf('插件[%s]未启用，无法同步后台资源', $this->code));
        }

        $this->info(sprintf('开始同步插件[%s]后台资源', $this->code));
        $synchronizer = app(AddonAdminResourceSynchronizer::class);
        $synchronizer->sync($this->code);
        if (!$enabled) {
            $synchronizer->disable($this->code);
        }
        $this->info(sprintf('插件[%s]后台资源同步完成', $this->code));

        return [
            'code' => $this->code,
            'synced' => true,
            'enabled' => $enabled,
        ];
    }
}
