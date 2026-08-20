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
    public const RUNTIME_PROTOCOL = 'ptadmin-addon-runtime@1';
    public const STATE_EXEMPT = 'exempt';
    public const STATE_ACTIVE = 'active';
    public const STATE_GRACE = 'grace';
    public const STATE_OFFLINE_GRACE = 'offline_grace';
    public const STATE_LEGACY_REVIEW = 'legacy_review';
    public const STATE_UNKNOWN = 'unknown';
    public const STATE_BLOCKED = 'blocked';

    private ApplicationInstanceProviderInterface $instance;
    private Filesystem $filesystem;
    private AddonInstallationRegistry $installations;

    public function __construct(
        ApplicationInstanceProviderInterface $instance,
        ?Filesystem $filesystem = null,
        ?AddonInstallationRegistry $installations = null
    )
    {
        $this->instance = $instance;
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->installations = $installations ?? new AddonInstallationRegistry($this->filesystem);
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
        }
        unset($license);

        return [
            'application_instance_id' => $currentInstanceId,
            'results' => array_values($results),
        ];
    }

    /** @return array<string, mixed> */
    public function activate(string $code, string $licenseCode): array
    {
        if ($this->isLocalAddon($code)) {
            throw new AddonException(sprintf('插件[%s]属于本地插件，不使用 PTAdmin 平台授权。', $code));
        }

        $result = AddonApi::activateAddonLicense($this->activationPayload($code, $licenseCode));
        $this->storeActivation($code, $result);
        $this->installations->promoteToPlatform($code);

        return $this->status($code) ?? [];
    }

    /** @return array<string, mixed> */
    public function verify(string $code, ?int $addonVersionId = null): array
    {
        if ($this->isLocalAddon($code)) {
            throw new AddonException(sprintf('插件[%s]属于本地插件，不使用 PTAdmin 平台授权。', $code));
        }

        $license = $this->read($code);
        if (null === $license) {
            throw new AddonException(sprintf('插件[%s]尚未激活应用实例授权。', $code));
        }
        if ((string) ($license['application_instance_id'] ?? '') !== $this->instance->applicationInstanceId()) {
            throw new AddonException(sprintf('插件[%s]的授权已绑定其他应用，请重新购买授权。', $code));
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

    /**
     * 返回插件启动所依据的本地授权状态。
     *
     * 平台决策通过状态同步更新后，可写入同一状态文件；在尚未完成首次
     * 同步时保持 unknown 或 legacy_review，不能因网络不可达立即阻断。
     *
     * @return array<string, mixed>
     */
    public function runtimeStatus(string $code): array
    {
        $manifest = Addon::getAddons()[$code] ?? [];
        $manifest = is_array($manifest) ? $manifest : [];
        $installation = $this->installations->get($code) ?? [];
        $managementScope = $this->installations->resolveManagementScope($installation);
        if (AddonInstallationRegistry::MANAGEMENT_LOCAL === $managementScope) {
            return $this->runtimeState($code, self::STATE_EXEMPT, 'LOCAL_ADDON', [
                'management_scope' => $managementScope,
            ]);
        }

        $policy = trim((string) ($installation['release_license_policy'] ?? $manifest['release_license_policy'] ?? ''));
        $required = $this->requiresApplicationLicense($code);
        $license = $this->read($code);

        if (null !== $license) {
            if ((string) ($license['application_instance_id'] ?? '') !== $this->instance->applicationInstanceId()) {
                return $this->runtimeState($code, self::STATE_BLOCKED, 'INSTANCE_MISMATCH');
            }
            if (true === ($license['allow_run'] ?? false)) {
                $lastVerifiedAt = (int) ($license['last_verified_at'] ?? 0);
                $state = $lastVerifiedAt > 0 && $lastVerifiedAt + $this->verificationInterval($license) >= time()
                    ? self::STATE_ACTIVE
                    : self::STATE_OFFLINE_GRACE;

                return $this->runtimeState($code, $state, 'ACTIVE', [
                    'valid_until' => (int) ($license['valid_until'] ?? 0),
                    'last_verified_at' => $lastVerifiedAt,
                ]);
            }

            return $this->runtimeState($code, self::STATE_BLOCKED, (string) ($license['reason_code'] ?? 'EXPIRED'));
        }

        $decision = $this->readRuntimeDecision($code);
        if ([] !== $decision) {
            if (self::STATE_GRACE === ($decision['state'] ?? null)
                && (int) ($decision['grace_ends_at'] ?? 0) > 0
                && (int) $decision['grace_ends_at'] < time()) {
                $decision['state'] = self::STATE_BLOCKED;
                $decision['reason_code'] = 'GRACE_EXPIRED';
            }
            if (in_array($decision['state'] ?? null, [self::STATE_ACTIVE, self::STATE_OFFLINE_GRACE], true)
                && (int) ($decision['valid_until'] ?? 0) > 0
                && (int) $decision['valid_until'] < time()) {
                return $this->runtimeState($code, self::STATE_UNKNOWN, 'PLATFORM_DECISION_EXPIRED');
            }

            return $decision;
        }

        if (AddonInstallationRegistry::MANAGEMENT_LEGACY_UNKNOWN === $managementScope) {
            return $this->runtimeState($code, self::STATE_LEGACY_REVIEW, 'LEGACY_INSTALLATION', [
                'management_scope' => $managementScope,
            ]);
        }

        if (!$required && '' === $policy) {
            return $this->runtimeState($code, self::STATE_LEGACY_REVIEW, 'LEGACY_INSTALLATION');
        }

        if (!$required && 'legacy_review' === $policy) {
            return $this->runtimeState($code, self::STATE_LEGACY_REVIEW, 'LEGACY_INSTALLATION');
        }

        return $this->runtimeState($code, self::STATE_UNKNOWN, 'AWAITING_PLATFORM_DECISION');
    }

    /**
     * 保存平台签发的插件运行决策。决策验签失败时不会覆盖上一份有效状态。
     *
     * @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    public function applyRuntimeDecision(array $decision): array
    {
        $normalized = $this->normalizeRuntimeDecision($decision);
        if ($this->isLocalAddon((string) $normalized['addon_code'])) {
            throw new AddonException(sprintf(
                '插件[%s]属于本地插件，不接受 PTAdmin 平台授权决策。',
                (string) $normalized['addon_code']
            ));
        }
        if (!$this->verifyRuntimeDecision($normalized) || !$this->matchesInstallation($normalized)) {
            throw new AddonException(sprintf(
                '插件[%s]的平台运行授权决策签名无效。',
                (string) ($normalized['addon_code'] ?? '')
            ));
        }

        $this->installations->promoteToPlatform((string) $normalized['addon_code']);

        return $this->writeRuntimeDecision((string) $normalized['addon_code'], $normalized);
    }

    public function assertCanRun(string $code): void
    {
        $runtime = $this->runtimeStatus($code);
        if (self::STATE_BLOCKED === ($runtime['state'] ?? null)) {
            throw new AddonException(sprintf('插件[%s]授权状态为%s，当前不可运行。', $code, (string) ($runtime['reason_code'] ?? 'BLOCKED')));
        }
        if (self::STATE_OFFLINE_GRACE === ($runtime['state'] ?? null)
            && (int) ($runtime['valid_until'] ?? 0) < time()) {
            throw new AddonException(sprintf('插件[%s]授权离线宽限期已过期，请等待后台状态同步或重新激活。', $code));
        }
    }

    /**
     * 检查插件是否可以在宿主启动阶段注册服务提供者。
     * 启动和业务请求阶段都不访问平台，授权决策由请求结束后的批量同步更新。
     */
    public function assertCanBoot(string $code): void
    {
        $runtime = $this->runtimeStatus($code);
        if (self::STATE_BLOCKED === ($runtime['state'] ?? null)) {
            throw new AddonException(sprintf(
                '插件[%s]授权状态为%s，当前不可加载。',
                $code,
                (string) ($runtime['reason_code'] ?? 'BLOCKED')
            ));
        }
        if (self::STATE_OFFLINE_GRACE === ($runtime['state'] ?? null)
            && (int) ($runtime['valid_until'] ?? 0) < time()) {
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
    private function activationPayload(string $code, string $licenseCode): array
    {
        $payload = [
            'license_code' => $licenseCode,
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

    /** @param array<string, mixed> $license */
    private function verificationInterval(array $license): int
    {
        return max(300, (int) ($license['verification_interval'] ?? 86400));
    }

    private function isLocalAddon(string $code): bool
    {
        return AddonInstallationRegistry::MANAGEMENT_LOCAL === $this->installations->managementScope($code);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function runtimeState(string $code, string $state, string $reasonCode, array $extra = []): array
    {
        return array_merge([
            'addon_code' => $code,
            'state' => $state,
            'reason_code' => $reasonCode,
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function readRuntimeDecision(string $code): array
    {
        $path = $this->runtimeDecisionPath($code);
        if (!$this->filesystem->isFile($path)) {
            return [];
        }

        try {
            $decision = json_decode($this->filesystem->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [];
        }

        if (!is_array($decision) || (string) ($decision['addon_code'] ?? '') !== $code) {
            return [];
        }

        try {
            $normalized = $this->normalizeRuntimeDecision($decision);
        } catch (AddonException $exception) {
            return [];
        }

        return $this->verifyRuntimeDecision($normalized) && $this->matchesInstallation($normalized) ? $normalized : [];
    }

    /** @param array<string, mixed> $decision
     *  @return array<string, mixed>
     */
    private function writeRuntimeDecision(string $code, array $decision): array
    {
        $decision['addon_code'] = $code;
        $path = $this->runtimeDecisionPath($code);
        $this->filesystem->ensureDirectoryExists(dirname($path));
        $payload = json_encode(
            $decision,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (false === $this->filesystem->put($path, $payload, true)) {
            throw new AddonException(sprintf('插件[%s]运行授权状态保存失败。', $code));
        }
        @chmod($path, 0600);

        return $decision;
    }

    private function runtimeDecisionPath(string $code): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $code)) {
            throw new AddonException('插件编码格式无效。');
        }

        return rtrim((string) config(
            'addon.license_runtime_storage_path',
            storage_path('app/ptadmin/addon/runtime')
        ), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$code.'.json';
    }

    /**
     * @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    private function normalizeRuntimeDecision(array $decision): array
    {
        $state = (string) ($decision['state'] ?? '');
        if (!in_array($state, [
            self::STATE_EXEMPT,
            self::STATE_ACTIVE,
            self::STATE_GRACE,
            self::STATE_OFFLINE_GRACE,
            self::STATE_LEGACY_REVIEW,
            self::STATE_BLOCKED,
        ], true)) {
            throw new AddonException('平台运行授权决策状态无效。');
        }

        $normalized = [
            'protocol' => (string) ($decision['protocol'] ?? ''),
            'application_instance_id' => (string) ($decision['application_instance_id'] ?? ''),
            'addon_code' => (string) ($decision['addon_code'] ?? ''),
            'version' => (string) ($decision['version'] ?? ''),
            'package_hash' => (string) ($decision['package_hash'] ?? ''),
            'state' => $state,
            'reason_code' => (string) ($decision['reason_code'] ?? ''),
            'grace_started_at' => (int) ($decision['grace_started_at'] ?? 0),
            'grace_ends_at' => (int) ($decision['grace_ends_at'] ?? 0),
            'valid_until' => (int) ($decision['valid_until'] ?? 0),
            'issued_at' => (int) ($decision['issued_at'] ?? 0),
            'policy_version' => (int) ($decision['policy_version'] ?? 0),
            'signature' => (string) ($decision['signature'] ?? ''),
        ];

        if (self::RUNTIME_PROTOCOL !== $normalized['protocol']
            || $this->instance->applicationInstanceId() !== $normalized['application_instance_id']
            || '' === $normalized['addon_code']
            || $normalized['issued_at'] <= 0
            || $normalized['policy_version'] <= 0
            || '' === $normalized['signature']) {
            throw new AddonException('平台运行授权决策字段不完整或与当前应用不匹配。');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $decision */
    private function verifyRuntimeDecision(array $decision): bool
    {
        $signature = base64_decode((string) $decision['signature'], true);
        $publicKey = $this->platformPublicKey();
        if (!is_string($signature) || '' === $publicKey) {
            return false;
        }

        $payload = $decision;
        unset($payload['signature']);
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $canonical) {
            return false;
        }

        return 1 === openssl_verify($canonical, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    }

    private function platformPublicKey(): string
    {
        $configured = trim((string) config('addon.platform_license_public_key', ''));
        if ('' === $configured) {
            return '';
        }
        if (is_file($configured) && is_readable($configured)) {
            $content = file_get_contents($configured);

            return false === $content ? '' : trim($content);
        }

        return str_replace('\\n', "\n", $configured);
    }

    /** @param array<string, mixed> $decision */
    private function matchesInstallation(array $decision): bool
    {
        $installation = $this->installations->get((string) $decision['addon_code']);
        if (!is_array($installation)) {
            return true;
        }
        $installedVersion = trim((string) ($installation['version'] ?? ''));
        $decisionVersion = trim((string) ($decision['version'] ?? ''));
        if ('' !== $installedVersion && '' !== $decisionVersion && $installedVersion !== $decisionVersion) {
            return false;
        }
        $installedHash = trim((string) ($installation['package_hash'] ?? ''));
        $decisionHash = trim((string) ($decision['package_hash'] ?? ''));

        return '' === $installedHash || '' === $decisionHash || hash_equals($installedHash, $decisionHash);
    }
}
