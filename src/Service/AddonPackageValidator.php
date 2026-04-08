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
                    throw new AddonException("插件【{$manifest['code']}】要求 PHP 版本满足 {$constraint}，当前为 ".PHP_VERSION);
                }

                continue;
            }

            $installedVersion = $this->detectComposerPackageVersion((string) $target);
            if (null === $installedVersion) {
                $this->info("未解析到宿主依赖【{$target}】版本，跳过兼容性校验");

                continue;
            }

            if (!$this->satisfies($installedVersion, $constraint)) {
                throw new AddonException("插件【{$manifest['code']}】要求依赖【{$target}】版本满足 {$constraint}，当前为 {$installedVersion}");
            }
        }
    }

    private function validateDependencies(array $manifest): void
    {
        $plugins = (array) data_get($manifest, 'dependencies.plugins', $manifest['require'] ?? []);
        foreach ($plugins as $code => $constraint) {
            if (\is_int($code)) {
                if (!Addon::hasAddon((string) $constraint)) {
                    throw new AddonException("插件【{$manifest['code']}】依赖插件【{$constraint}】未安装或未启用");
                }

                continue;
            }

            if (!Addon::hasAddon((string) $code)) {
                throw new AddonException("插件【{$manifest['code']}】依赖插件【{$code}】未安装或未启用");
            }
            if (\is_string($constraint) && '' !== trim($constraint) && !Addon::checkAddonVersion((string) $code, $constraint)) {
                throw new AddonException("插件【{$manifest['code']}】依赖插件【{$code}】版本需满足 {$constraint}");
            }
        }
    }

    private function validatePurchase(array $manifest): void
    {
        $marketplace = (array) ($manifest['marketplace'] ?? []);
        if ([] === $marketplace) {
            $this->info("插件【{$manifest['code']}】未声明云端市场信息，跳过云端登记与购买校验");

            return;
        }

        try {
            if (!AddonApi::getAddonCodeExists((string) $manifest['code'])) {
                $this->info("插件【{$manifest['code']}】未在云端登记，按本地插件继续安装，跳过购买校验");

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
            $message = "插件【{$manifest['code']}】未购买，无法安装";
            if ('' !== $buyUrl) {
                $message .= "，请前往购买：{$buyUrl}";
            }

            throw new AddonException($message);
        } catch (AddonException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->info("插件【{$manifest['code']}】未完成云端登记校验，按本地插件继续安装");
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
