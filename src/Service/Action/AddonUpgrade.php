<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2025 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Support\Facades\Http;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonUtil;

final class AddonUpgrade extends AbstractAddonAction
{
    /** @var int */
    private $progress = 0;

    /** @var string */
    private $hash = '';

    public function handle($versionId = 0, $force = false): ?bool
    {
        if (!Addon::hasInstalledAddon($this->code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $this->code]));
        }
        if ($this->isDevelop() && !$force) {
            $this->error(__('ptadmin-addon::messages.addon.develop_force', ['code' => $this->code]));

            return null;
        }

        $this->filesystem->ensureDirectoryExists($this->action->getStorePath());
        $currentPath = Addon::getAddonPath($this->code);
        $currentVersion = Addon::getAddonVersion($this->code);
        $disabled = file_exists($currentPath.\DIRECTORY_SEPARATOR.'disable');

        $this->info(__('ptadmin-addon::messages.action.backup_start'));
        $backupPath = $this->backupAddon($currentPath);
        $sourceDir = $this->downloadAndUnzip($versionId);
        $newConfig = AddonUtil::readAddonConfig($sourceDir);
        if (null === $newConfig || ($newConfig['code'] ?? null) !== $this->code) {
            throw new AddonException(__('ptadmin-addon::messages.addon.upgrade_invalid', ['code' => $this->code]));
        }

        try {
            $this->info(__('ptadmin-addon::messages.action.upgrade_start', ['code' => $this->code]));
            $this->replaceAddon($sourceDir, $currentPath, $disabled);

            $installer = Addon::getAddonInstaller($this->code);
            if (null !== $installer) {
                $installer->upgrade($currentVersion, $newConfig['version'] ?? null);
            }
            $this->info(__('ptadmin-addon::messages.action.upgrade_done', [
                'from' => $currentVersion,
                'to' => $newConfig['version'] ?? 'unknown',
            ]));
        } catch (\Throwable $exception) {
            $this->restoreBackup($backupPath, $currentPath, $disabled);

            throw $exception;
        }

        return true;
    }

    /**
     * 判断当前插件是否处于开发模式.
     */
    protected function isDevelop(): bool
    {
        $addons = Addon::getInstalledAddons();

        return (bool) ($addons[$this->code]['develop'] ?? false);
    }

    private function backupAddon(string $currentPath): string
    {
        $backupPath = $this->action->getStorePath('backup'.\DIRECTORY_SEPARATOR.basename($currentPath));
        $this->filesystem->ensureDirectoryExists(\dirname($backupPath));
        $this->filesystem->copyDirectory($currentPath, $backupPath);

        return $backupPath;
    }

    private function restoreBackup(string $backupPath, string $targetPath, bool $disabled): void
    {
        $this->filesystem->deleteDirectory($targetPath);
        $this->filesystem->moveDirectory($backupPath, $targetPath);
        if ($disabled) {
            $this->filesystem->put($targetPath.\DIRECTORY_SEPARATOR.'disable', '');
        }
    }

    private function replaceAddon(string $sourceDir, string $targetPath, bool $disabled): void
    {
        $this->filesystem->deleteDirectory($targetPath);
        $this->filesystem->ensureDirectoryExists(\dirname($targetPath));
        if (!$this->filesystem->moveDirectory($sourceDir, $targetPath)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.replace_failed', ['code' => $this->code]));
        }
        if ($disabled) {
            $this->filesystem->put($targetPath.\DIRECTORY_SEPARATOR.'disable', '');
        }
    }

    private function downloadAndUnzip($versionId): string
    {
        $data = AddonApi::getAddonDownloadUrl([
            'code' => $this->code,
            'addon_version_id' => $versionId,
        ]);
        if (!isset($data['url']) || '' === $data['url']) {
            throw new AddonException(__('ptadmin-addon::messages.addon.download_url_failed', ['code' => $this->code]));
        }
        $this->hash = $data['hash'] ?? '';
        $this->downloadPackage($data['url']);

        $dirname = $this->getUnzipDirname();
        if (null === $dirname) {
            throw new AddonException(__('ptadmin-addon::messages.addon.unzip_failed', ['code' => $this->code]));
        }

        return $dirname;
    }

    private function downloadPackage(string $url): void
    {
        $limit = 0;
        $this->info(__('ptadmin-addon::messages.action.download_start'));

        download:
        $response = Http::withOptions([
            'progress' => function ($total, $downloaded): void {
                if ($total > 0) {
                    $progress = (int) ($downloaded / $total * 100);
                    if ($progress !== $this->progress) {
                        $this->progress = $progress;
                        $this->info(__('ptadmin-addon::messages.action.download_progress', ['progress' => $progress]));
                    }
                }
            },
        ])->get($url);

        if (!$response->successful()) {
            throw new AddonException(__('ptadmin-addon::messages.addon.download_failed'));
        }

        $body = $response->body();
        file_put_contents($this->getDownloadFilename(), $body);
        if ('' !== $this->hash && md5($body) !== $this->hash) {
            if ($limit >= 5) {
                throw new AddonException(__('ptadmin-addon::messages.addon.verify_failed', ['code' => $this->code]));
            }
            ++$limit;

            goto download;
        }

        $this->unzip($this->getDownloadFilename(), $this->action->getStorePath('package'));
    }

    private function getDownloadFilename(): string
    {
        return $this->action->getStorePath($this->filename);
    }

    private function getUnzipDirname(): ?string
    {
        clearstatcache();
        $base = $this->action->getStorePath('package');
        if (!is_dir($base)) {
            return null;
        }
        $dirs = scandir($base);
        foreach ($dirs as $dir) {
            if ('.' !== $dir && '..' !== $dir && is_dir($base.\DIRECTORY_SEPARATOR.$dir)) {
                return $base.\DIRECTORY_SEPARATOR.$dir;
            }
        }

        return null;
    }
}
