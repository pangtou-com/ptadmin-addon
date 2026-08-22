<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 宿主侧插件安装状态。
 *
 * 插件目录表示代码已部署，安装记录表示安装器生命周期已成功执行。
 */
final class AddonInstallationRegistry
{
    public const MANAGEMENT_PLATFORM = 'platform';
    public const MANAGEMENT_LOCAL = 'local';
    public const MANAGEMENT_LEGACY_UNKNOWN = 'legacy_unknown';

    /** @var Filesystem */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function isInstalled(string $code): bool
    {
        return null !== $this->get($code);
    }

    public function get(string $code): ?array
    {
        $path = $this->recordPath($code);
        if (!$this->filesystem->isFile($path)) {
            return null;
        }

        try {
            $record = json_decode($this->filesystem->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new AddonException(
                __('ptadmin-addon::messages.addon.installation_state_invalid', ['code' => $code]),
                20000,
                $exception
            );
        }

        if (!is_array($record) || (string) ($record['code'] ?? '') !== $code) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_invalid', ['code' => $code]));
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function markInstalled(
        string $code,
        ?string $version = null,
        string $source = 'package',
        array $metadata = []
    ): void
    {
        $previous = $this->get($code);
        $now = gmdate(DATE_ATOM);
        $record = [
            'code' => $code,
            'version' => $version,
            'source' => $source,
            'management_scope' => $this->scopeForInstallation($source, $previous),
            'installed_at' => $previous['installed_at'] ?? $now,
            'updated_at' => $now,
        ];

        foreach ([
            'addon_version_id',
            'package_hash',
            'release_license_policy',
            'entitlement_id',
            'entitlement_scope',
            'policy_receipt',
        ] as $field) {
            if (array_key_exists($field, $metadata) && null !== $metadata[$field] && '' !== $metadata[$field]) {
                $record[$field] = $metadata[$field];
                continue;
            }
            if (is_array($previous) && array_key_exists($field, $previous)) {
                $record[$field] = $previous[$field];
            }
        }

        $this->write($code, $record);
    }

    public function managementScope(string $code): string
    {
        return $this->resolveManagementScope($this->get($code));
    }

    /** @param array<string, mixed>|null $installation */
    public function resolveManagementScope(?array $installation): string
    {
        $scope = is_array($installation) ? (string) ($installation['management_scope'] ?? '') : '';
        if (in_array($scope, [self::MANAGEMENT_PLATFORM, self::MANAGEMENT_LOCAL, self::MANAGEMENT_LEGACY_UNKNOWN], true)) {
            return $scope;
        }

        $source = is_array($installation) ? (string) ($installation['source'] ?? '') : '';
        if ('marketplace' === $source) {
            return self::MANAGEMENT_PLATFORM;
        }
        if ('local_package' === $source) {
            return self::MANAGEMENT_LOCAL;
        }

        return self::MANAGEMENT_LEGACY_UNKNOWN;
    }

    public function promoteToPlatform(string $code): void
    {
        $record = $this->get($code);
        if (!is_array($record) || self::MANAGEMENT_LOCAL === $this->resolveManagementScope($record)) {
            return;
        }

        $record['management_scope'] = self::MANAGEMENT_PLATFORM;
        $record['updated_at'] = gmdate(DATE_ATOM);
        $this->write($code, $record);
    }

    /**
     * 从插件清单提取宿主可保存的授权发布元数据。
     *
     * 清单是安装时的输入，不是运行时的可信授权来源；签名权益仍需由平台
     * 通过安装或状态同步接口返回。这里仅保存兼容信息，便于历史安装归档。
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function metadataFromManifest(array $manifest, string $source = 'package'): array
    {
        $policy = trim((string) ($manifest['release_license_policy'] ?? ''));
        if ('' === $policy) {
            $policy = true === ($manifest['license_required'] ?? false)
                ? 'license_required'
                : ('marketplace' === $source ? 'free_perpetual' : 'legacy_review');
        }

        $marketplace = is_array($manifest['marketplace'] ?? null) ? $manifest['marketplace'] : [];
        $entitlement = is_array($manifest['entitlement'] ?? null) ? $manifest['entitlement'] : [];

        return [
            'addon_version_id' => (int) ($manifest['addon_version_id'] ?? $marketplace['addon_version_id'] ?? 0),
            'package_hash' => (string) ($manifest['package_hash'] ?? $marketplace['checksum'] ?? ''),
            'release_license_policy' => $policy,
            'entitlement_id' => (string) ($entitlement['id'] ?? ''),
            'entitlement_scope' => (string) ($entitlement['scope'] ?? ''),
            'policy_receipt' => (string) ($entitlement['policy_receipt'] ?? ''),
        ];
    }

    public function forget(string $code): void
    {
        $path = $this->recordPath($code);
        if ($this->filesystem->isFile($path) && !$this->filesystem->delete($path)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_delete_failed', ['code' => $code]));
        }
    }

    private function write(string $code, array $record): void
    {
        $directory = $this->directory();
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
        if (!$this->filesystem->isDirectory($directory) || !is_writable($directory)) {
            throw new AddonException(__('ptadmin-addon::messages.package.directory_not_writable', ['path' => $directory]));
        }

        $payload = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === $this->filesystem->put($this->recordPath($code), $payload, true)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_write_failed', ['code' => $code]));
        }
    }

    /** @param array<string, mixed>|null $previous */
    private function scopeForInstallation(string $source, ?array $previous): string
    {
        if ('marketplace' === $source) {
            return self::MANAGEMENT_PLATFORM;
        }
        if ('local_package' === $source) {
            return self::MANAGEMENT_LOCAL;
        }

        return $this->resolveManagementScope($previous);
    }

    private function recordPath(string $code): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.code_invalid', ['code' => $code]));
        }

        return $this->directory().DIRECTORY_SEPARATOR.$code.'.json';
    }

    private function directory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'ptadmin'.DIRECTORY_SEPARATOR.'addon'.DIRECTORY_SEPARATOR.'installations');
    }
}
