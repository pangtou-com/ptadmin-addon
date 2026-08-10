<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Contracts\ApplicationInstanceProviderInterface;
use PTAdmin\Addon\Exception\AddonException;

final class AddonLicenseService
{
    public const PROTOCOL = 'ptadmin-addon-license@1';

    private ApplicationInstanceProviderInterface $instance;
    private Filesystem $filesystem;

    public function __construct(ApplicationInstanceProviderInterface $instance, ?Filesystem $filesystem = null)
    {
        $this->instance = $instance;
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /** @return array<string, mixed> */
    public function licenses(string $code): array
    {
        $payload = AddonApi::getAddonLicenses($code);
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        $currentInstanceId = $this->instance->applicationInstanceId();
        foreach ($results as &$license) {
            if (!is_array($license)) {
                continue;
            }
            $boundInstanceId = (string) ($license['application_instance_id'] ?? '');
            $status = (string) ($license['status'] ?? '');
            $expiresAt = (int) ($license['expires_at'] ?? 0);
            $usable = in_array($status, ['pending_binding', 'active'], true)
                && (0 === $expiresAt || $expiresAt > time());
            $license['is_current_instance'] = '' !== $boundInstanceId && $boundInstanceId === $currentInstanceId;
            $license['can_activate'] = $usable
                && ('' === $boundInstanceId || 'pending_activation' === (string) ($license['activation_status'] ?? ''));
            $license['can_transfer'] = $usable
                && '' !== $boundInstanceId
                && $boundInstanceId !== $currentInstanceId
                && (int) ($license['transfer_used'] ?? 0) < (int) ($license['transfer_limit'] ?? 0);
        }
        unset($license);

        return [
            'application_instance_id' => $currentInstanceId,
            'results' => array_values($results),
        ];
    }

    /** @return array<string, mixed> */
    public function activate(string $code, int $licenseId): array
    {
        $result = AddonApi::activateAddonLicense($this->activationPayload($code, $licenseId));
        $this->storeActivation($code, $result);

        return $this->status($code) ?? [];
    }

    /** @return array<string, mixed> */
    public function transfer(string $code, int $licenseId, string $reason): array
    {
        $payload = $this->activationPayload($code, $licenseId);
        $payload['reason'] = $reason;
        $result = AddonApi::transferAddonLicense($payload);
        $this->storeActivation($code, $result);

        return $this->status($code) ?? [];
    }

    /** @return array<string, mixed> */
    public function verify(string $code, ?int $addonVersionId = null): array
    {
        $license = $this->read($code);
        if (null === $license) {
            throw new AddonException(sprintf('插件[%s]尚未激活应用实例授权。', $code));
        }
        if ((string) ($license['application_instance_id'] ?? '') !== $this->instance->applicationInstanceId()) {
            throw new AddonException(sprintf('插件[%s]的授权不属于当前应用，请迁移授权或重新购买。', $code));
        }

        $timestamp = time();
        $activationToken = (string) ($license['activation_token'] ?? '');
        if ('' === $activationToken) {
            throw new AddonException(sprintf('插件[%s]的激活凭证无效，请重新激活。', $code));
        }
        $signaturePayload = implode("\n", [
            $activationToken,
            $code,
            $this->instance->applicationInstanceId(),
            (string) $timestamp,
        ]);
        $request = [
            'activation_token' => $activationToken,
            'code' => $code,
            'application_instance_id' => $this->instance->applicationInstanceId(),
            'timestamp' => $timestamp,
            'signature' => $this->instance->sign($signaturePayload),
        ];
        if (null !== $addonVersionId && $addonVersionId > 0) {
            $request['addon_version_id'] = $addonVersionId;
        }
        $domain = $this->observedDomain();
        if ('' !== $domain) {
            $request['domain'] = $domain;
        }

        $result = AddonApi::verifyAddonLicense($request);
        if (true !== ($result['allow_run'] ?? false)) {
            throw new AddonException(sprintf(
                '插件[%s]授权校验失败：%s',
                $code,
                (string) ($result['reason_code'] ?? 'UNKNOWN')
            ));
        }
        $license['last_verified_at'] = $timestamp;
        $license['valid_until'] = (int) ($result['valid_until'] ?? 0);
        $license['reason_code'] = (string) ($result['reason_code'] ?? 'ACTIVE');
        $license['allow_run'] = true;
        $this->write($code, $license);

        return array_merge($this->status($code) ?? [], $result);
    }

    /** @return array<string, mixed>|null */
    public function status(string $code): ?array
    {
        $license = $this->read($code);
        if (null === $license) {
            return null;
        }

        $license['is_current_instance'] = (string) ($license['application_instance_id'] ?? '')
            === $this->instance->applicationInstanceId();
        $license['within_offline_grace'] = (int) ($license['valid_until'] ?? 0) >= time();
        unset($license['activation_token']);

        return $license;
    }

    public function assertCanRun(string $code): void
    {
        $license = $this->read($code);
        if (null === $license) {
            if ($this->requiresApplicationLicense($code)) {
                throw new AddonException(sprintf('插件[%s]需要应用实例授权，请先激活授权。', $code));
            }

            return;
        }
        if ((string) ($license['application_instance_id'] ?? '') !== $this->instance->applicationInstanceId()) {
            throw new AddonException(sprintf('插件[%s]授权已绑定其他应用，请迁移授权或重新购买。', $code));
        }

        $lastVerifiedAt = (int) ($license['last_verified_at'] ?? 0);
        $verificationInterval = max(300, (int) ($license['verification_interval'] ?? 86400));
        if ($lastVerifiedAt + $verificationInterval > time()) {
            return;
        }

        try {
            $this->verify($code);
        } catch (AddonException $exception) {
            if (20000 === $exception->getCode() && (int) ($license['valid_until'] ?? 0) >= time()) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * 检查插件是否可以在宿主启动阶段注册服务提供者。
     * 启动阶段不访问平台网络，周期校验仍由请求中间件负责。
     */
    public function assertCanBoot(string $code): void
    {
        $license = $this->read($code);
        if (null === $license) {
            if ($this->requiresApplicationLicense($code)) {
                throw new AddonException(sprintf('插件[%s]需要应用实例授权，请先激活授权。', $code));
            }

            return;
        }
        if ((string) ($license['application_instance_id'] ?? '') !== $this->instance->applicationInstanceId()) {
            throw new AddonException(sprintf('插件[%s]授权已绑定其他应用，请迁移授权或重新购买。', $code));
        }
        if ((int) ($license['valid_until'] ?? 0) < time()) {
            throw new AddonException(sprintf('插件[%s]授权离线宽限期已过期，请重新验证。', $code));
        }
    }

    public function requiresApplicationLicense(string $code): bool
    {
        $addons = Addon::getAddons();
        $manifest = is_array($addons[$code] ?? null) ? $addons[$code] : [];

        return true === ($manifest['license_required'] ?? false)
            || self::PROTOCOL === trim((string) ($manifest['license_protocol'] ?? ''));
    }

    /** @return array<string, mixed>|null */
    private function read(string $code): ?array
    {
        $path = $this->path($code);
        if (!$this->filesystem->exists($path)) {
            return null;
        }
        try {
            $license = json_decode($this->filesystem->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new AddonException(sprintf('插件[%s]本地授权凭证无法读取。', $code), 20000, $exception);
        }
        if (!is_array($license) || (string) ($license['addon_code'] ?? '') !== $code) {
            throw new AddonException(sprintf('插件[%s]本地授权凭证无效。', $code));
        }

        return $license;
    }

    /** @param array<string, mixed> $result */
    private function storeActivation(string $code, array $result): void
    {
        $activationToken = (string) ($result['activation_token'] ?? '');
        if ('' === $activationToken) {
            throw new AddonException(sprintf('插件[%s]激活响应缺少激活凭证。', $code));
        }
        $now = time();
        $offlineGraceSeconds = max(300, (int) ($result['offline_grace_seconds'] ?? 604800));
        $license = [
            'license_id' => (int) ($result['license_id'] ?? 0),
            'purchase_id' => (int) ($result['purchase_id'] ?? 0),
            'addon_code' => $code,
            'application_instance_id' => (string) ($result['application_instance_id'] ?? ''),
            'activation_token' => $activationToken,
            'activation_status' => (string) ($result['activation_status'] ?? 'active'),
            'application_name' => (string) ($result['application_name'] ?? ''),
            'last_seen_domain' => $result['last_seen_domain'] ?? null,
            'transfer_used' => (int) ($result['transfer_used'] ?? 0),
            'transfer_limit' => (int) ($result['transfer_limit'] ?? 0),
            'activation_token_version' => (int) ($result['activation_token_version'] ?? 0),
            'verification_interval' => max(300, (int) ($result['verification_interval'] ?? 86400)),
            'offline_grace_seconds' => $offlineGraceSeconds,
            'last_verified_at' => $now,
            'valid_until' => $now + $offlineGraceSeconds,
            'allow_run' => true,
            'reason_code' => 'ACTIVE',
        ];
        if ($license['license_id'] <= 0) {
            throw new AddonException(sprintf('插件[%s]激活响应缺少授权 ID。', $code));
        }
        if ('' === $license['application_instance_id']) {
            throw new AddonException(sprintf('插件[%s]激活响应缺少应用实例。', $code));
        }
        if ($license['application_instance_id'] !== $this->instance->applicationInstanceId()) {
            throw new AddonException(sprintf('插件[%s]激活响应的应用实例与当前宿主不一致。', $code));
        }
        $this->write($code, $license);
    }

    /** @return array<string, mixed> */
    private function activationPayload(string $code, int $licenseId): array
    {
        $payload = [
            'license_id' => $licenseId,
            'code' => $code,
            'application_instance_id' => $this->instance->applicationInstanceId(),
            'application_name' => (string) config('app.name', 'PTAdmin'),
            'instance_public_key' => $this->instance->publicKey(),
        ];
        $domain = $this->observedDomain();
        if ('' !== $domain) {
            $payload['domain'] = $domain;
        }

        return $payload;
    }

    /** @param array<string, mixed> $license */
    private function write(string $code, array $license): void
    {
        $path = $this->path($code);
        $this->filesystem->ensureDirectoryExists(dirname($path));
        $written = $this->filesystem->put(
            $path,
            json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            true
        );
        if (false === $written) {
            throw new AddonException(sprintf('插件[%s]本地授权凭证保存失败。', $code));
        }
        @chmod($path, 0600);
    }

    private function path(string $code): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $code)) {
            throw new AddonException('插件编码格式无效。');
        }

        return rtrim((string) config('addon.license_storage_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$code.'.json';
    }

    private function observedDomain(): string
    {
        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
