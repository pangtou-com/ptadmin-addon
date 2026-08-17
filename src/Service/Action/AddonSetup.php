<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonInstallationRegistry;
use PTAdmin\Addon\Service\AddonPackageValidator;

/**
 * 对已部署的插件执行安装生命周期，不覆盖插件目录。
 */
final class AddonSetup extends AbstractAddonAction
{
    public function handle(bool $force = false): ?bool
    {
        if (!Addon::hasInstalledAddon($this->code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $this->code]));
        }

        $registry = app(AddonInstallationRegistry::class);
        if ($registry->isInstalled($this->code) && !$force) {
            throw new AddonException(__('ptadmin-addon::messages.addon.setup_done_force', ['code' => $this->code]));
        }

        $manifest = Addon::getInstalledAddons()[$this->code] ?? null;
        if (!is_array($manifest)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $this->code]));
        }
        (new AddonPackageValidator(function (string $message): void {
            $this->info($message);
        }))->validate($manifest);

        $installer = new AddonInstall($this->code, $this->action);

        return $installer->handle(true, 'existing');
    }
}
