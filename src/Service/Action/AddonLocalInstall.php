<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonPackageValidator;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonUtil;

final class AddonLocalInstall extends AbstractAddonAction
{
    public function handle(string $packageFile, bool $force = false): ?string
    {
        if (!is_file($packageFile)) {
            throw new AddonException(__('ptadmin-addon::messages.package.local_not_exists', ['file' => $packageFile]));
        }
        if (!\in_array(strtolower((string) pathinfo($packageFile, PATHINFO_EXTENSION)), ['zip'], true)) {
            throw new AddonException(__('ptadmin-addon::messages.package.local_zip_only'));
        }

        $this->filesystem->ensureDirectoryExists($this->action->getStorePath('package'));
        $this->info(__('ptadmin-addon::messages.action.unpack_local'));
        $this->unzip($packageFile, $this->action->getStorePath('package'));

        $sourceDir = $this->resolveSourceDir();
        $config = AddonUtil::readAddonConfig($sourceDir);
        if (null === $config) {
            throw new AddonException(__('ptadmin-addon::messages.package.manifest_missing'));
        }

        $code = (string) $config['code'];
        $this->code = $code;
        $this->validatePackageIntegrity($packageFile, $config);

        if (Addon::hasInstalledAddon($code)) {
            if (!$force) {
                throw new AddonException(__('ptadmin-addon::messages.addon.installed_force', ['code' => $code]));
            }
            $this->info(__('ptadmin-addon::messages.action.overwrite_start', ['code' => $code]));
            $this->action->backupCurrentAddon($code);
        }

        $target = base_path('addons'.\DIRECTORY_SEPARATOR.$config['base_path']);
        $this->info(__('ptadmin-addon::messages.addon.copy_target', ['path' => $target]));
        if (is_dir($target)) {
            $this->filesystem->deleteDirectory($target);
        }
        $this->filesystem->ensureDirectoryExists(\dirname($target));
        if (!$this->filesystem->moveDirectory($sourceDir, $target)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.write_failed', ['code' => $code]));
        }

        $this->refreshAddonState();
        (new AddonInstall($code, $this->action))->handle();

        return $code;
    }

    private function resolveSourceDir(): string
    {
        $packageBase = $this->action->getStorePath('package');
        $manifest = $packageBase.\DIRECTORY_SEPARATOR.'manifest.json';
        if (is_file($manifest)) {
            return $packageBase;
        }

        $dirs = array_diff(scandir($packageBase), ['.', '..']);
        foreach ($dirs as $dir) {
            $path = $packageBase.\DIRECTORY_SEPARATOR.$dir;
            if (is_dir($path) && is_file($path.\DIRECTORY_SEPARATOR.'manifest.json')) {
                return $path;
            }
        }

        throw new AddonException(__('ptadmin-addon::messages.package.manifest_not_found'));
    }

    private function validatePackageIntegrity(string $packageFile, array $config): void
    {
        $marketplace = $config['marketplace'] ?? [];
        $checksum = (string) ($marketplace['checksum'] ?? '');
        if ('' !== $checksum) {
            $this->validateChecksum($packageFile, $checksum);
        }
        (new AddonPackageValidator(function (string $message): void {
            $this->info($message);
        }))->validate($config, true);
    }

    private function validateChecksum(string $packageFile, string $checksum): void
    {
        list($algorithm, $hash) = array_pad(explode(':', $checksum, 2), 2, null);
        $algorithm = $algorithm ?: 'sha256';
        if (null === $hash || '' === $hash) {
            throw new AddonException(__('ptadmin-addon::messages.package.checksum_format_invalid'));
        }
        if (!\in_array($algorithm, hash_algos(), true)) {
            throw new AddonException(__('ptadmin-addon::messages.package.checksum_algorithm_unsupported', ['algorithm' => $algorithm]));
        }
        if (hash_file($algorithm, $packageFile) !== $hash) {
            throw new AddonException(__('ptadmin-addon::messages.package.checksum_failed'));
        }
    }

    private function refreshAddonState(): void
    {
        Addon::reset();
        AddonDirectivesManage::getInstance()->reset();
        AddonInjectsManage::getInstance()->reset();
        AddonHooksManage::getInstance()->reset();
    }
}
