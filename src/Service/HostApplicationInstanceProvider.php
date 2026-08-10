<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\ApplicationInstanceProviderInterface;
use PTAdmin\Addon\Exception\AddonException;

final class HostApplicationInstanceProvider implements ApplicationInstanceProviderInterface
{
    public function applicationInstanceId(): string
    {
        return (string) $this->identity()['application_instance_id'];
    }

    public function publicKey(): string
    {
        return (string) $this->identity()['public_key'];
    }

    public function sign(string $payload): string
    {
        $serviceClass = 'PTAdmin\\Admin\\Services\\ApplicationInstanceService';
        if (!class_exists($serviceClass)) {
            throw new AddonException('当前宿主不支持应用实例授权，请先升级 ptadmin/admin。');
        }

        return app($serviceClass)->sign($payload);
    }

    /** @return array<string, mixed> */
    private function identity(): array
    {
        if (!function_exists('ptadmin_application_instance')) {
            throw new AddonException('当前宿主不支持应用实例授权，请先升级 ptadmin/admin。');
        }

        $identity = ptadmin_application_instance();
        if (!is_array($identity)
            || '' === (string) ($identity['application_instance_id'] ?? '')
            || '' === (string) ($identity['public_key'] ?? '')
        ) {
            throw new AddonException('当前应用实例身份无效，请检查 PTAdmin 安装状态。');
        }

        return $identity;
    }
}
