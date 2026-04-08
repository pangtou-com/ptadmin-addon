<?php

declare(strict_types=1);

namespace Addon\Test;

use PTAdmin\Addon\Service\BaseInstaller;

class Installer extends BaseInstaller
{
    public function install(): void
    {
        file_put_contents(base_path('addons/Test/install.log'), 'installed');
    }

    public function init(): void
    {
        file_put_contents(base_path('addons/Test/init.log'), 'initialized');
    }

    public function upgrade(?string $fromVersion = null, ?string $toVersion = null): void
    {
        file_put_contents(base_path('addons/Test/upgrade.log'), ($fromVersion ?? 'unknown').'->'.($toVersion ?? 'unknown'));
    }

    public function uninstall(): void
    {
        file_put_contents(base_path('addons/Test/uninstall.log'), 'uninstalled');
        file_put_contents(base_path('addon-uninstall.log'), 'uninstalled');
    }
}
