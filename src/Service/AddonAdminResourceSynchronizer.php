<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Addon;

/**
 * 插件后台资源同步器。
 *
 * 仅在宿主项目安装了 `ptadmin/admin` 且已绑定资源服务时生效，
 * 在纯插件环境下会自动跳过，不引入额外强依赖。
 */
class AddonAdminResourceSynchronizer
{
    private const RESOURCE_SERVICE_CONTRACT = 'PTAdmin\\Contracts\\Auth\\AdminResourceServiceInterface';

    public function sync(string $addonCode): void
    {
        $service = $this->resolveResourceService();
        if (null === $service) {
            return;
        }

        $bootstrap = Addon::getAddonBootstrap($addonCode);
        if (null === $bootstrap || !method_exists($bootstrap, 'getAdminResourceDefinitions')) {
            return;
        }

        $definitions = $bootstrap->getAdminResourceDefinitions($addonCode, $this->resolveAddonInfo($addonCode));
        $service->syncAddonResources($addonCode, \is_array($definitions) ? $definitions : array());
    }

    public function disable(string $addonCode): void
    {
        $service = $this->resolveResourceService();
        if (null === $service) {
            return;
        }

        $service->disableAddonResources($addonCode);
    }

    public function delete(string $addonCode): void
    {
        $service = $this->resolveResourceService();
        if (null === $service) {
            return;
        }

        $service->deleteByAddonCode($addonCode);
    }

    /**
     * @return mixed|null
     */
    private function resolveResourceService()
    {
        if (!app()->bound(self::RESOURCE_SERVICE_CONTRACT)) {
            return null;
        }

        $service = app(self::RESOURCE_SERVICE_CONTRACT);
        if (!\is_object($service)) {
            return null;
        }

        if (
            !method_exists($service, 'syncAddonResources')
            || !method_exists($service, 'disableAddonResources')
            || !method_exists($service, 'deleteByAddonCode')
        ) {
            return null;
        }

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAddonInfo(string $addonCode): array
    {
        if (Addon::hasAddon($addonCode)) {
            return Addon::getAddon($addonCode)->getAddons();
        }

        $addons = Addon::getInstalledAddons();

        return isset($addons[$addonCode]) && \is_array($addons[$addonCode])
            ? $addons[$addonCode]
            : array();
    }
}
