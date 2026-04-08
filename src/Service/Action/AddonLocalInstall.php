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
            throw new AddonException("本地安装包不存在：{$packageFile}");
        }
        if (!\in_array(strtolower((string) pathinfo($packageFile, PATHINFO_EXTENSION)), ['zip'], true)) {
            throw new AddonException('本地安装仅支持 zip 压缩包');
        }

        $this->filesystem->ensureDirectoryExists($this->action->getStorePath('package'));
        $this->info('开始解压本地安装包');
        $this->unzip($packageFile, $this->action->getStorePath('package'));

        $sourceDir = $this->resolveSourceDir();
        $config = AddonUtil::readAddonConfig($sourceDir);
        if (null === $config) {
            throw new AddonException('本地安装包缺少有效 manifest.json');
        }

        $code = (string) $config['code'];
        $this->code = $code;
        $this->validatePackageIntegrity($packageFile, $config);

        if (Addon::hasInstalledAddon($code)) {
            if (!$force) {
                throw new AddonException("插件【{$code}】已安装，请使用 --force 覆盖安装");
            }
            $this->info("检测到插件【{$code}】已安装，开始覆盖安装");
            $this->action->backupCurrentAddon($code);
        }

        $target = base_path('addons'.\DIRECTORY_SEPARATOR.$config['base_path']);
        $this->info("开始复制插件到目录：【{$target}】");
        if (is_dir($target)) {
            $this->filesystem->deleteDirectory($target);
        }
        $this->filesystem->ensureDirectoryExists(\dirname($target));
        if (!$this->filesystem->moveDirectory($sourceDir, $target)) {
            throw new AddonException("插件【{$code}】安装失败，无法写入插件目录");
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

        throw new AddonException('本地安装包解压后未找到 manifest.json');
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
            throw new AddonException('本地安装包 checksum 格式无效');
        }
        if (!\in_array($algorithm, hash_algos(), true)) {
            throw new AddonException("不支持的 checksum 算法：{$algorithm}");
        }
        if (hash_file($algorithm, $packageFile) !== $hash) {
            throw new AddonException('本地安装包完整性校验失败');
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
