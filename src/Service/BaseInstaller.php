<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

/**
 * 插件安装器基类.
 */
abstract class BaseInstaller
{
    public function beforeInstall(): bool
    {
        return true;
    }

    public function install(): void
    {
    }

    public function init(): void
    {
    }

    public function upgrade(?string $fromVersion = null, ?string $toVersion = null): void
    {
    }

    public function uninstall(): void
    {
    }
}
