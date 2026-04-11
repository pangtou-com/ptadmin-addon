<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Composer\InstalledVersions;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Exception\AddonException;

final class AddonPackageValidator
{
    /** @var callable|null */
    private $logger;

    public function __construct(callable $logger = null)
    {
        $this->logger = $logger;
    }

    public function validate(array $manifest, bool $verifyPurchase = false): void
    {
        $this->validateCompatibility($manifest);
        $this->validateDependencies($manifest);

        if ($verifyPurchase) {
            $this->validatePurchase($manifest);
        }
    }

    private function validateCompatibility(array $manifest): void
    {
        $compatibility = (array) ($manifest['compatibility'] ?? []);
        foreach ($compatibility as $target => $constraint) {
            if (!\is_string($constraint) || '' === trim($constraint)) {
                continue;
            }

            if ('php' === $target) {
                if (!$this->satisfies(PHP_VERSION, $constraint)) {
                    throw new AddonException(__('ptadmin-addon::messages.validator.php_constraint_failed', [
                        'code' => $manifest['code'],
                        'constraint' => $constraint,
                    ]));
                }

                continue;
            }

            $installedVersion = $this->detectComposerPackageVersion((string) $target);
            if (null === $installedVersion) {
                $this->info(__('ptadmin-addon::messages.validator.host_version_skip', ['target' => $target]));

                continue;
            }

            if (!$this->satisfies($installedVersion, $constraint)) {
                throw new AddonException(__('ptadmin-addon::messages.validator.host_constraint_failed', [
                    'code' => $manifest['code'],
                    'target' => $target,
                    'constraint' => $constraint,
                    'version' => $installedVersion,
                ]));
            }
        }
    }

    private function validateDependencies(array $manifest): void
    {
        $plugins = (array) data_get($manifest, 'dependencies.plugins', $manifest['require'] ?? []);
        foreach ($plugins as $code => $constraint) {
            if (\is_int($code)) {
                if (!Addon::hasAddon((string) $constraint)) {
                    throw new AddonException(__('ptadmin-addon::messages.validator.dependency_missing', [
                        'code' => $manifest['code'],
                        'target' => $constraint,
                    ]));
                }

                continue;
            }

            if (!Addon::hasAddon((string) $code)) {
                throw new AddonException(__('ptadmin-addon::messages.validator.dependency_missing', [
                    'code' => $manifest['code'],
                    'target' => $code,
                ]));
            }
            if (\is_string($constraint) && '' !== trim($constraint) && !Addon::checkAddonVersion((string) $code, $constraint)) {
                throw new AddonException(__('ptadmin-addon::messages.validator.dependency_version_failed', [
                    'code' => $manifest['code'],
                    'target' => $code,
                    'constraint' => $constraint,
                ]));
            }
        }
    }

    private function validatePurchase(array $manifest): void
    {
        $marketplace = (array) ($manifest['marketplace'] ?? []);
        if ([] === $marketplace) {
            $this->info(__('ptadmin-addon::messages.validator.marketplace_skip', ['code' => $manifest['code']]));

            return;
        }

        try {
            if (!AddonApi::getAddonCodeExists((string) $manifest['code'])) {
                $this->info(__('ptadmin-addon::messages.validator.cloud_unregistered_skip', ['code' => $manifest['code']]));

                return;
            }

            $result = AddonApi::verifyAddonPurchase([
                'code' => $manifest['code'],
                'product_id' => $marketplace['product_id'] ?? null,
            ]);
            if ($this->isPurchaseAllowed($result)) {
                return;
            }

            $buyUrl = (string) (data_get($result, 'buy_url')
                ?? data_get($result, 'purchase_url')
                ?? data_get($result, 'url')
                ?? '');
            $message = '' !== $buyUrl
                ? __('ptadmin-addon::messages.validator.purchase_required_with_url', [
                    'code' => $manifest['code'],
                    'url' => $buyUrl,
                ])
                : __('ptadmin-addon::messages.validator.purchase_required', ['code' => $manifest['code']]);

            throw new AddonException($message);
        } catch (AddonException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->info(__('ptadmin-addon::messages.validator.cloud_verify_skip', ['code' => $manifest['code']]));
        }
    }

    private function isPurchaseAllowed(array $result): bool
    {
        $flags = [
            data_get($result, 'verified'),
            data_get($result, 'purchased'),
            data_get($result, 'is_purchased'),
            data_get($result, 'is_buy'),
            data_get($result, 'has_buy'),
            data_get($result, 'allow_install'),
        ];

        foreach ($flags as $flag) {
            if (null === $flag) {
                continue;
            }

            return \in_array($flag, [true, 1, '1', 'true'], true);
        }

        return true;
    }

    private function detectComposerPackageVersion(string $package): ?string
    {
        $configuredVersion = config('addon.host_versions.'.$package);
        if (\is_string($configuredVersion) && '' !== trim($configuredVersion)) {
            return $configuredVersion;
        }

        $configuredVersion = config('ptadmin.host_versions.'.$package);
        if (\is_string($configuredVersion) && '' !== trim($configuredVersion)) {
            return $configuredVersion;
        }

        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package) ?? InstalledVersions::getVersion($package);
    }

    private function satisfies(string $actual, string $constraint): bool
    {
        $segments = preg_split('/\s*,\s*|\s+/', trim($constraint)) ?: [];
        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }

            if (!preg_match('/^(>=|<=|>|<|==|=|!=)?\s*(.+)$/', $segment, $matches)) {
                return false;
            }
            $operator = $matches[1] ?: '>=';
            $version = trim($matches[2]);
            if ('=' === $operator) {
                $operator = '==';
            }
            if (!version_compare(ltrim($actual, 'v'), ltrim($version, 'v'), $operator)) {
                return false;
            }
        }

        return true;
    }

    private function info(string $message): void
    {
        if (null !== $this->logger) {
            \call_user_func($this->logger, $message);
        }
    }
}
